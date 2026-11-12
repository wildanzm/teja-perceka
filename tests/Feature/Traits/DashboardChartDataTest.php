<?php

use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Traits\DashboardChartData;
use Illuminate\Support\Carbon;

test('dashboard chart data normalizes periods and calculates ledger totals', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $accounts = createCoreAccounts();
    $unit = BusinessUnit::factory()->create();

    JournalEntry::factory()->credit(120_000)->for($unit)->create([
        'voucher_number' => 'DXX001',
        'transaction_date' => '2026-05-15',
        'description' => 'Pendapatan',
        'account_id' => $accounts['revenue']->id,
    ]);
    JournalEntry::factory()->debit(45_000)->for($unit)->create([
        'voucher_number' => 'KXX001',
        'transaction_date' => '2026-05-15',
        'description' => 'Beban',
        'account_id' => $accounts['expense']->id,
    ]);

    $harness = new class
    {
        use DashboardChartData;

        public function dispatch(string $event, mixed ...$params): void {}

        public function data(string $period, ?int $unitId): array
        {
            return $this->dashboardViewData($period, $unitId);
        }
    };

    expect($harness->data('invalid', $unit->id))->toMatchArray([
        'period' => 'bulanan',
        'income' => 120_000.0,
        'expenses' => 45_000.0,
        'netIncome' => 75_000.0,
    ]);
});
