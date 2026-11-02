<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Support\VoucherNumber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VouchersRenumber extends Command
{
    protected $signature = 'vouchers:renumber
        {--apply : Terapkan perubahan (default hanya dry-run)}
        {--prefix= : Batasi ke prefix voucher, misal DBS}
        {--month= : Batasi ke bulan YYYY-MM, misal 2026-10}
        {--unit= : Batasi ke business_unit_id}';

    protected $description = 'Rapikan nomor voucher per scope (prefix + bulan + unit). Dry-run default; tanpa hapus, tanpa ubah nominal.';

    public function handle(): int
    {
        $scopes = $this->detectScopes();

        if ($scopes->isEmpty()) {
            $this->info('Tidak ada scope yang perlu dirapikan.');

            return self::SUCCESS;
        }

        $this->table(
            ['Prefix', 'Bulan', 'Unit', 'Voucher', 'Bolong', 'Duplikat'],
            $scopes->map(fn (array $scope) => [
                $scope['prefix'],
                $scope['month'],
                $scope['unit'] ?? '-',
                $scope['count'],
                $scope['gaps'] ? implode(',', $scope['gaps']) : '-',
                $scope['duplicates'] ? implode(',', $scope['duplicates']) : '-',
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->warn('Dry-run. Tidak ada data diubah. Jalankan ulang dengan --apply untuk menerapkan.');

            return self::SUCCESS;
        }

        $backupPath = 'voucher-number-backup-'.Carbon::now()->format('Ymd-His').'.json';
        $backup = $scopes->mapWithKeys(fn (array $scope) => [
            $scope['prefix'].'|'.$scope['month'].'|'.($scope['unit'] ?? 'null') => $this->scopeQuery($scope)
                ->orderBy('id')
                ->get(['id', 'voucher_number', 'transaction_date', 'debit', 'credit'])
                ->toArray(),
        ]);

        Storage::disk('local')->put($backupPath, $backup->toJson(JSON_PRETTY_PRINT));
        $this->info('Backup nomor voucher lama: storage/app/'.$backupPath);

        foreach ($scopes as $scope) {
            DB::transaction(function () use ($scope): void {
                $before = $this->scopeQuery($scope)->get(['id', 'debit', 'credit']);
                $beforeIds = $before->pluck('id')->sort()->values()->all();

                VoucherNumber::renumberScope($scope['prefix'], $scope['month'], $scope['unit']);

                $after = $this->scopeQuery($scope)->get(['id', 'debit', 'credit']);
                $afterIds = $after->pluck('id')->sort()->values()->all();

                $unchanged = $beforeIds === $afterIds
                    && (float) $before->sum('debit') === (float) $after->sum('debit')
                    && (float) $before->sum('credit') === (float) $after->sum('credit');

                if (! $unchanged) {
                    throw new \RuntimeException(
                        'Verifikasi gagal untuk '.$scope['prefix'].' '.$scope['month'].' unit '.($scope['unit'] ?? '-').' — dibatalkan, tidak ada yang berubah.'
                    );
                }
            });

            $this->line('Dirapikan: '.$scope['prefix'].' '.$scope['month'].' unit '.($scope['unit'] ?? '-'));
        }

        $this->info('Selesai. Baris dan nominal tidak berubah; hanya voucher_number.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{prefix: string, month: string, unit: int|null, count: int, gaps: array<int, int>, duplicates: array<int, int>}>
     */
    private function detectScopes(): Collection
    {
        $query = JournalEntry::query();

        if ($this->option('prefix')) {
            $query->where('voucher_number', 'like', strtoupper((string) $this->option('prefix')).'%');
        }

        if ($this->option('unit') !== null) {
            $query->where('business_unit_id', (int) $this->option('unit'));
        }

        $rows = $query->get(['voucher_number', 'transaction_date', 'business_unit_id', 'daily_transaction_id', 'description'])
            ->filter(fn ($row) => VoucherNumber::prefixOf((string) $row->voucher_number) !== null);

        return $rows
            ->groupBy(fn ($row) => VoucherNumber::prefixOf($row->voucher_number).'|'.substr($row->transaction_date, 0, 7).'|'.$row->business_unit_id)
            ->when($this->option('month'), fn ($scopes) => $scopes->filter(
                fn ($group, string $key) => explode('|', $key)[1] === $this->option('month')
            ))
            ->map(function ($group, string $key) {
                [$prefix, $month, $unit] = explode('|', $key);
                $numbers = $group->map(fn ($row) => (int) substr($row->voucher_number, strlen($prefix)))->sort()->values();
                $duplicates = $group
                    ->groupBy(fn ($row) => $row->voucher_number)
                    ->filter(fn ($same) => $same->map(fn ($row) => ($row->daily_transaction_id ?? 'null').'|'.$row->description)->unique()->count() > 1)
                    ->keys()
                    ->map(fn ($number) => (int) substr((string) $number, strlen($prefix)))
                    ->values()
                    ->all();
                $gaps = array_values(array_diff(range(1, (int) $numbers->max()), $numbers->all()));

                return [
                    'prefix' => $prefix,
                    'month' => $month,
                    'unit' => $unit === '' ? null : (int) $unit,
                    'count' => $numbers->count(),
                    'gaps' => $gaps,
                    'duplicates' => $duplicates,
                ];
            })
            ->filter(fn (array $scope) => $scope['gaps'] !== [] || $scope['duplicates'] !== [])
            ->values();
    }

    /**
     * @param  array{prefix: string, month: string, unit: int|null}  $scope
     */
    private function scopeQuery(array $scope): Builder
    {
        return JournalEntry::where('voucher_number', 'like', $scope['prefix'].'%')
            ->whereBetween('transaction_date', [
                $scope['month'].'-01',
                Carbon::parse($scope['month'].'-01')->endOfMonth()->format('Y-m-d'),
            ])
            ->when($scope['unit'] !== null,
                fn ($query) => $query->where('business_unit_id', $scope['unit']),
                fn ($query) => $query->whereNull('business_unit_id'));
    }
}
