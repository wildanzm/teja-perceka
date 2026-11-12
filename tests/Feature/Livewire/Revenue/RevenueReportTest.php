<?php

use App\Livewire\Revenue\RevenueReport;
use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use App\Models\TransactionCategory;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('bendahara'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('selecting a weekly unit switches daily mode to weekly', function () {
    $unit = BusinessUnit::where('code', 'TPS')->firstOrFail();

    Livewire::test(RevenueReport::class)
        ->set('unit_id', $unit->id)
        ->assertSet('mode', 'weekly');
});

test('the period label follows the selected month', function () {
    $label = Livewire::test(RevenueReport::class)
        ->set('mode', 'monthly')
        ->set('month', '2026-09')
        ->get('periodLabel');

    expect($label)->toBe('September 2026');
});

test('category rows aggregate the selected unit and month', function () {
    $unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $category = TransactionCategory::factory()->for($unit)->create(['name' => 'Tiket SC']);
    $transaction = DailyTransaction::factory()->for($unit)->create([
        'transaction_date' => '2026-09-10',
    ]);

    TransactionItem::factory()->for($transaction, 'dailyTransaction')->for($category)->create([
        'quantity' => 4,
        'unit_price' => 5_000,
        'subtotal' => 20_000,
    ]);

    $data = Livewire::test(RevenueReport::class)
        ->set('unit_id', $unit->id)
        ->set('mode', 'monthly')
        ->set('month', '2026-09')
        ->get('reportData');

    expect($data['categoryRows'])->toHaveCount(1)
        ->and($data['categoryRows']->first()['totalQuantity'])->toBe(4)
        ->and($data['categoryRows']->first()['subtotal'])->toBe(20_000.0);
});
