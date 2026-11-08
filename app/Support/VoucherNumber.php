<?php

namespace App\Support;

use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for voucher number sequencing.
 *
 * Scope: prefix + YYYY-MM(date) + business_unit_id.
 * Insert-and-shift: a new voucher takes its chronological slot by date,
 * later vouchers shift by +N so date and number order always agree.
 */
class VoucherNumber
{
    /**
     * @return array{prefix: string, number: string, sequence: int}
     */
    public static function next(string $prefix, string $date, ?int $businessUnitId = null): array
    {
        $number = self::nextBatch($prefix, $date, 1, $businessUnitId)[0];

        return [
            'prefix' => $prefix,
            'number' => $number,
            'sequence' => (int) substr($number, strlen($prefix)),
        ];
    }

    /**
     * Extract the letter prefix of a voucher number (e.g. DSB003 -> DSB).
     * Returns null for non-standard formats (e.g. ADJ-...), which are skipped.
     */
    public static function prefixOf(string $voucherNumber): ?string
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $voucherNumber, $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * Close numbering gaps in one scope (prefix + YYYY-MM + unit) so vouchers
     * stay exactly 001..N in chronological order. Called after every delete
     * or cross-month move. Scopes that are already sequential are untouched.
     */
    public static function renumberScope(string $prefix, string $month, ?int $unitId = null): void
    {
        $monthStart = $month.'-01';
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->format('Y-m-d');

        $rows = JournalEntry::where('voucher_number', 'like', $prefix.'%')
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<=', $monthEnd)
            ->when($unitId !== null,
                fn ($query) => $query->where('business_unit_id', $unitId),
                fn ($query) => $query->whereNull('business_unit_id'))
            ->lockForUpdate()
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'voucher_number', 'transaction_date', 'daily_transaction_id', 'description'])
            ->filter(fn ($row) => self::prefixOf((string) $row->voucher_number) === $prefix);

        $pairs = $rows
            ->groupBy(fn ($row) => $row->voucher_number.'|'.($row->daily_transaction_id ?? 'null').'|'.$row->description)
            ->map(fn ($group) => [
                'ids' => $group->pluck('id')->all(),
                'old' => $group->first()->voucher_number,
                'date' => $group->min('transaction_date'),
                'firstId' => $group->min('id'),
            ])
            ->sortBy([['date', 'asc'], ['firstId', 'asc']])
            ->values();

        $baseOffset = 0;
        if ($prefix === 'DBM' && BumdesCashBalance::getOpeningBalance($monthStart) > 0) {
            $baseOffset = 1;
        }

        // Compute every target number first, then apply. Writing inside the loop let a
        // group whose old number already equalled its new number be skipped while a
        // different group was simultaneously assigned that same number, producing two
        // distinct vouchers sharing one number in the same month.
        $assignments = [];
        $taken = [];

        foreach ($pairs as $index => $pair) {
            $new = $prefix.str_pad($index + 1 + $baseOffset, 3, '0', STR_PAD_LEFT);

            if (isset($taken[$new])) {
                // Should not happen with positional numbering, but a collision here would
                // silently merge two vouchers, so make it impossible rather than hoped-for.
                continue;
            }

            $taken[$new] = true;

            if ($pair['old'] !== $new) {
                $assignments[$new] = $pair['ids'];
            }
        }

        foreach ($assignments as $new => $ids) {
            JournalEntry::whereIn('id', $ids)->update(['voucher_number' => $new]);
        }
    }

    /**
     * Reserve $count sequential numbers at the chronological position.
     *
     * @return string[]
     */
    public static function nextBatch(string $prefix, string $date, int $count, ?int $businessUnitId = null): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Batch voucher minimal 1.');
        }

        return DB::transaction(function () use ($prefix, $date, $count, $businessUnitId) {
            $day = Carbon::parse($date)->format('Y-m-d');

            $query = JournalEntry::where('voucher_number', 'like', $prefix.'%')
                ->whereDate('transaction_date', '>=', substr($day, 0, 7).'-01')
                ->whereDate('transaction_date', '<', Carbon::parse($day)->startOfMonth()->addMonth()->format('Y-m-d'))
                ->lockForUpdate();

            if ($businessUnitId !== null) {
                $query->where('business_unit_id', $businessUnitId);
            } else {
                $query->whereNull('business_unit_id');
            }

            // One voucher = one voucher_number (possibly many debit/credit rows), ordered chronologically.
            // Identity of a logical voucher: same number + same daily + same
            // description. Every writer in this codebase stores one
            // description per voucher, so more than one combination means rows
            // of distinct transactions got merged under one number.
            $vouchers = [];
            foreach ((clone $query)->orderBy('transaction_date')->orderBy('id')->get(['id', 'voucher_number', 'transaction_date', 'daily_transaction_id', 'description']) as $row) {
                if (self::prefixOf((string) $row->voucher_number) !== $prefix) {
                    continue;
                }

                $key = $row->voucher_number;
                $vouchers[$key]['date'] ??= Carbon::parse($row->transaction_date)->format('Y-m-d');
                $vouchers[$key]['ids'][] = $row->id;
                $vouchers[$key]['combos'][$row->daily_transaction_id.'|'.$row->description] = true;
            }

            // Never silently grow a corruption: one voucher shared by several
            // transactions means numbering already broke.
            foreach ($vouchers as $number => $voucher) {
                if (count($voucher['combos'] ?? []) > 1) {
                    throw new \RuntimeException(
                        "Duplicate voucher {$number} shared by several transactions. "
                        .'Silakan perbaiki data jurnal secara manual.'
                    );
                }
            }
            $ordered = array_values($vouchers);

            // Base offset: if BUMDes has prior cash balance, reserve DBM001 for Saldo Kas
            $baseOffset = 0;
            if ($prefix === 'DBM') {
                $monthStart = substr($day, 0, 7).'-01';
                if (BumdesCashBalance::getOpeningBalance($monthStart) > 0) {
                    $baseOffset = 1;
                }
            }

            // Insertion rank: after the last voucher with an older date.
            $k = 0;
            foreach ($ordered as $voucher) {
                if ($voucher['date'] < $day) {
                    $k++;
                } else {
                    break;
                }
            }

            // Shift backwards (largest first) to avoid name collisions.
            for ($i = count($ordered) - 1; $i >= $k; $i--) {
                $old = (int) substr(array_keys($vouchers)[$i], strlen($prefix));
                JournalEntry::whereIn('id', $ordered[$i]['ids'])
                    ->update(['voucher_number' => $prefix.str_pad($old + $count, 3, '0', STR_PAD_LEFT)]);
            }

            // Pure appends continue after the highest existing number so gaps
            // left by deletions can never collide. Middle inserts take over
            // the slot number vacated by the shifted voucher — using the
            // positional index here collides with an existing number whenever
            // an earlier deletion left a gap in the sequence.
            $maxExisting = 0;
            foreach (array_keys($vouchers) as $key) {
                $maxExisting = max($maxExisting, (int) substr($key, strlen($prefix)));
            }
            $isAppend = $k >= count($ordered);
            $base = $isAppend
                ? max($maxExisting, $baseOffset + $k)
                : (int) substr(array_keys($vouchers)[$k], strlen($prefix));

            // Middle inserts reuse the number vacated by the shift above,
            // appends continue after the highest number.
            $start = $isAppend ? $base + 1 : $base;

            $numbers = [];
            for ($i = 0; $i < $count; $i++) {
                $numbers[] = $prefix.str_pad($start + $i, 3, '0', STR_PAD_LEFT);
            }

            return $numbers;
        });
    }
}
