<?php

use App\Livewire\Transactions\RevenueTab;
use App\Models\BusinessUnit;
use App\Models\CategoryPriceHistory;
use App\Models\DailyTransaction;
use App\Models\JournalEntry;
use App\Models\TransactionCategory;
use App\Models\TransactionItem;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();

    $this->actingAs(createUserWithRole('bendahara'));
});

test('category rows aggregate item quantities across the selected period', function () {
    $unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $category = TransactionCategory::factory()->for($unit)->create(['name' => 'Tiket SC']);

    CategoryPriceHistory::factory()->for($category)->create([
        'price' => 10_000,
        'effective_from' => '2026-01-01',
    ]);

    $transaction = DailyTransaction::factory()->for($unit)->create([
        'transaction_date' => '2026-09-10',
    ]);

    TransactionItem::factory()->for($transaction, 'dailyTransaction')->for($category)->create([
        'quantity' => 2,
        'unit_price' => 10_000,
        'subtotal' => 20_000,
    ]);
    TransactionItem::factory()->for($transaction, 'dailyTransaction')->for($category)->create([
        'quantity' => 3,
        'unit_price' => 10_000,
        'subtotal' => 30_000,
    ]);

    $rows = Livewire::test(RevenueTab::class, [
        'unitId' => $unit->id,
        'mode' => 'monthly',
        'month' => '2026-09',
    ])->get('reportData')['categoryRows'];

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['category'])->toBe('Tiket SC')
        ->and($rows->first()['totalQuantity'])->toBe(5)
        ->and($rows->first()['subtotal'])->toBe(50_000.0);
});

test('the BUMDes scope reports journal totals instead of category rows', function () {
    $accounts = createCoreAccounts();

    JournalEntry::factory()->debit(40_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-09-10',
        'account_id' => $accounts['expense']->id,
    ]);

    $data = Livewire::test(RevenueTab::class, [
        'unitId' => 'bumdes',
        'mode' => 'monthly',
        'month' => '2026-09',
    ])->get('reportData');

    expect($data['categoryRows'])->toBeEmpty()
        ->and($data['unitTotalExpense'])->toBe(40_000.0)
        ->and($data['netIncome'])->toBe(-40_000.0);
});
