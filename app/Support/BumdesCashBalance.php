<?php

namespace App\Support;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BumdesCashBalance
{
    /**
     * Calculate BUMDes opening cash balance prior to the given start date.
     *
     * Scope mirrors Laba Rugi exactly: KBM vouchers + manual DBM (unit-less,
     * no daily transaction) journals, plus every unit leg (daily legs and
     * manually recorded unit journals). Previously this only counted
     * daily_transaction legs, so manual unit expense journals (e.g. KSC)
     * were included in Laba Rugi's net income but missing from the next
     * month's "Saldo Kas" — the two reports disagreed.
     */
    public static function getOpeningBalance(Carbon|string $startDate): float
    {
        $day = Carbon::parse($startDate)->startOfDay()->format('Y-m-d');

        // BUMDes-scope journals: KBM + manual DBM (unit-less).
        $base = JournalEntry::whereDate('transaction_date', '<', $day)
            ->where(function ($query) {
                $query->where('voucher_number', 'like', 'KBM%')
                    ->orWhere(function ($sub) {
                        $sub->where('voucher_number', 'like', 'DBM%')
                            ->whereNull('daily_transaction_id')
                            ->whereNull('business_unit_id');
                    });
            });

        $revenue = (float) (clone $base)
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['pendapatan', 'pendapatan_lain']))
            ->sum(DB::raw('credit - debit'));
        $expenses = (float) (clone $base)
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['hpp', 'beban', 'beban_lain']))
            ->sum(DB::raw('debit - credit'));

        // Unit legs: daily legs plus manually recorded unit journals.
        // KBM legs are excluded here — they live in the BUMDes scope above.
        $unitLegs = JournalEntry::whereDate('transaction_date', '<', $day)
            ->where(function ($query) {
                $query->whereNotNull('daily_transaction_id')
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('business_unit_id')
                            ->where('voucher_number', 'not like', 'KBM%');
                    });
            });

        $unitRevenue = (float) (clone $unitLegs)
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['pendapatan', 'pendapatan_lain']))
            ->sum(DB::raw('credit - debit'));
        $unitExpenses = (float) (clone $unitLegs)
            ->whereHas('account', fn ($query) => $query->whereIn('type', ['hpp', 'beban', 'beban_lain']))
            ->sum(DB::raw('debit - credit'));

        $openingBalance = ($revenue + $unitRevenue) - ($expenses + $unitExpenses);

        return max(0.0, $openingBalance);
    }

    /**
     * Calculate net revenue per business unit for the specified date range.
     *
     * Same journal-ledger source as Laba Rugi (not header/item totals, which
     * can go stale): revenue journals - expense journals per unit.
     *
     * @return Collection<int, array{unit: BusinessUnit, amount: float}>
     */
    public static function getNetIncomePerUnit(Carbon|string $startDate, Carbon|string $endDate): Collection
    {
        $startDay = Carbon::parse($startDate)->startOfDay()->format('Y-m-d');
        $endDay = Carbon::parse($endDate)->endOfDay()->format('Y-m-d');

        $sums = JournalEntry::query()
            ->join('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->whereNotNull('journal_entries.business_unit_id')
            ->whereDate('journal_entries.transaction_date', '>=', $startDay)
            ->whereDate('journal_entries.transaction_date', '<=', $endDay)
            ->whereIn('accounts.type', ['pendapatan', 'pendapatan_lain', 'hpp', 'beban', 'beban_lain'])
            ->selectRaw('journal_entries.business_unit_id as unit_id, accounts.type as type, SUM(journal_entries.credit - journal_entries.debit) as net_credit, SUM(journal_entries.debit - journal_entries.credit) as net_debit')
            ->groupBy('journal_entries.business_unit_id', 'accounts.type')
            ->get()
            ->groupBy('unit_id');

        $units = BusinessUnit::orderBy('id')->get()->keyBy('id');
        $results = collect();

        foreach ($sums as $unitId => $rows) {
            $unit = $units->get($unitId);
            if (! $unit) {
                continue;
            }

            $revenue = (float) $rows->whereIn('type', ['pendapatan', 'pendapatan_lain'])->sum('net_credit');
            $expenses = (float) $rows->whereIn('type', ['hpp', 'beban', 'beban_lain'])->sum('net_debit');

            // Net unit revenue = ledger revenue - ledger expenses
            $netAmount = max(0.0, $revenue - $expenses);

            if ($netAmount > 0) {
                $results->push([
                    'unit' => $unit,
                    'amount' => $netAmount,
                ]);
            }
        }

        return $results;
    }

    /**
     * Get aggregate net unit income across all units within the specified period.
     */
    public static function getTotalNetUnitIncome(Carbon|string $startDate, Carbon|string $endDate): float
    {
        return (float) self::getNetIncomePerUnit($startDate, $endDate)->sum('amount');
    }

    /**
     * Generate 2 virtual opening balance journal lines.
     *
     * @return Collection<int, JournalEntry>
     */
    public static function makeOpeningBalanceEntries(Carbon|string $startDate, float $amount, string $mode = 'monthly', string $voucherNumber = 'DBM001'): Collection
    {
        if ($amount <= 0) {
            return collect();
        }

        $date = Carbon::parse($startDate)->startOfDay();
        $description = match ($mode) {
            'yearly' => 'Saldo Kas Tahun '.$date->copy()->subYear()->format('Y'),
            'semester' => 'Saldo Kas '.($date->month <= 6 ? 'Semester 2 '.$date->copy()->subYear()->format('Y') : 'Semester 1 '.$date->format('Y')),
            default => 'Saldo Kas '.$date->copy()->subMonth()->translatedFormat('F'),
        };

        $cashAccount = Account::where('code', '1-1100')->first();
        $equityAccount = Account::where('code', '3-2000')->first();

        if (! $cashAccount || ! $equityAccount) {
            return collect();
        }

        $cashEntry = new JournalEntry([
            'voucher_number' => $voucherNumber,
            'transaction_date' => $date,
            'description' => $description,
            'account_id' => $cashAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'business_unit_id' => null,
        ]);
        $cashEntry->setRelation('account', $cashAccount);
        $cashEntry->transaction_date = $date;

        $equityEntry = new JournalEntry([
            'voucher_number' => $voucherNumber,
            'transaction_date' => $date,
            'description' => $description,
            'account_id' => $equityAccount->id,
            'debit' => 0,
            'credit' => $amount,
            'business_unit_id' => null,
        ]);
        $equityEntry->setRelation('account', $equityAccount);
        $equityEntry->transaction_date = $date;

        return collect([$cashEntry, $equityEntry]);
    }

    /**
     * Generate virtual journal entries summarizing net revenue per business unit at the end of the period (DBM002, DBM003, etc.).
     *
     * @return Collection<int, JournalEntry>
     */
    public static function makeUnitRevenueEntries(Carbon|string $startDate, Carbon|string $endDate, int $startVoucherNumber = 2): Collection
    {
        $unitIncomes = self::getNetIncomePerUnit($startDate, $endDate);
        if ($unitIncomes->isEmpty()) {
            return collect();
        }

        $cashAccount = Account::where('code', '1-1100')->first();
        $revenueAccount = Account::where('code', '4-2000')->first();

        if (! $cashAccount || ! $revenueAccount) {
            return collect();
        }

        $entries = collect();
        $date = Carbon::parse($endDate)->endOfDay();
        $voucherCounter = $startVoucherNumber;

        foreach ($unitIncomes as $item) {
            $unit = $item['unit'];
            $amount = $item['amount'];
            $voucherNumber = sprintf('DBM%03d', $voucherCounter++);
            $description = 'Pendapatan '.$unit->name;

            $cashEntry = new JournalEntry([
                'voucher_number' => $voucherNumber,
                'transaction_date' => $date,
                'description' => $description,
                'account_id' => $cashAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'business_unit_id' => $unit->id,
            ]);
            $cashEntry->setRelation('account', $cashAccount);
            $cashEntry->setRelation('businessUnit', $unit);
            $cashEntry->transaction_date = $date;

            $revenueEntry = new JournalEntry([
                'voucher_number' => $voucherNumber,
                'transaction_date' => $date,
                'description' => $description,
                'account_id' => $revenueAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'business_unit_id' => $unit->id,
            ]);
            $revenueEntry->setRelation('account', $revenueAccount);
            $revenueEntry->setRelation('businessUnit', $unit);
            $revenueEntry->transaction_date = $date;

            $entries->push($cashEntry, $revenueEntry);
        }

        return $entries;
    }

    /**
     * Retrieve all virtual BUMDes journal entries (opening balance + unit revenue summaries).
     *
     * Virtual vouchers are numbered after the highest real manual DBM voucher
     * in the period, so they never collide with stored vouchers.
     *
     * @return Collection<int, JournalEntry>
     */
    public static function getBumdesVirtualEntries(Carbon|string $startDate, Carbon|string $endDate, string $mode = 'monthly'): Collection
    {
        $entries = collect();

        $startVoucherNumber = self::nextFreeDbmSequence($startDate, $endDate);
        // Opening balance is only applicable to periodic reporting (monthly, semester, annual)
        if (in_array($mode, ['monthly', 'semester', 'yearly'], true)) {
            $openingBalance = self::getOpeningBalance($startDate);
            if ($openingBalance > 0) {
                $entries = $entries->concat(self::makeOpeningBalanceEntries(
                    $startDate, $openingBalance, $mode, sprintf('DBM%03d', $startVoucherNumber)
                ));
                $startVoucherNumber++;
            }
        }

        $unitRevenueEntries = self::makeUnitRevenueEntries($startDate, $endDate, $startVoucherNumber);
        $entries = $entries->concat($unitRevenueEntries);

        return $entries;
    }

    /**
     * First unused DBM voucher number in the period. Real manual DBM vouchers
     * keep their numbers; virtual entries continue the sequence after them.
     */
    private static function nextFreeDbmSequence(Carbon|string $startDate, Carbon|string $endDate): int
    {
        $start = Carbon::parse($startDate)->format('Y-m-d');
        $end = Carbon::parse($endDate)->format('Y-m-d');

        $max = JournalEntry::where('voucher_number', 'like', 'DBM%')
            ->whereBetween('transaction_date', [$start, $end])
            ->pluck('voucher_number')
            ->map(fn ($number) => (int) substr((string) $number, 3))
            ->max();

        return (int) $max + 1;
    }

    /**
     * Merge real BUMDes journal transactions with virtual entries and sort them chronologically.
     */
    public static function attachToTransactions(
        Collection $transactions,
        Carbon|string $startDate,
        Carbon|string $endDate,
        string $mode = 'monthly',
        string $sortField = 'transaction_date',
        string $sortDirection = 'asc'
    ): Collection {
        $virtualEntries = self::getBumdesVirtualEntries($startDate, $endDate, $mode);
        if ($virtualEntries->isEmpty()) {
            return $transactions;
        }

        $allTransactions = $transactions->concat($virtualEntries);
        $isDescending = strtolower($sortDirection) === 'desc';

        return $allTransactions->sort(function ($first, $second) use ($sortField, $isDescending) {
            if ($sortField === 'voucher_number') {
                $comparison = strcmp($first->voucher_number, $second->voucher_number);
                if ($comparison !== 0) {
                    return $isDescending ? -$comparison : $comparison;
                }
            }

            $firstTimestamp = Carbon::parse($first->transaction_date)->timestamp;
            $secondTimestamp = Carbon::parse($second->transaction_date)->timestamp;
            if ($firstTimestamp !== $secondTimestamp) {
                return $isDescending ? ($secondTimestamp <=> $firstTimestamp) : ($firstTimestamp <=> $secondTimestamp);
            }

            $voucherComparison = strcmp($first->voucher_number, $second->voucher_number);
            if ($voucherComparison !== 0) {
                return $isDescending ? -$voucherComparison : $voucherComparison;
            }

            return $isDescending ? (($second->id ?? 0) <=> ($first->id ?? 0)) : (($first->id ?? 0) <=> ($second->id ?? 0));
        })->values();
    }
}
