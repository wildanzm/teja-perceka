<?php

use App\Livewire\UnitHead\RecordDailyTransaction;
use App\Models\BusinessUnit;
use App\Models\CategoryPriceHistory;
use App\Models\DailyTransaction;
use App\Models\TransactionCategory;
use App\Models\TransactionItem;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();

    $this->unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $this->head = createUserWithRole('kepala_unit', $this->unit);

    $this->category = TransactionCategory::factory()->for($this->unit)->create([
        'name' => 'Tiket Masuk',
    ]);

    CategoryPriceHistory::factory()->for($this->category)->create([
        'price' => 10_000,
        'effective_from' => '2026-01-01',
    ]);
});

test('unit heads can submit a daily income transaction', function () {
    $this->actingAs($this->head);

    Livewire::test(RecordDailyTransaction::class)
        ->set('transactionDate', '2026-09-10')
        ->set("inputs.{$this->category->id}.quantity", '3')
        ->call('submit')
        ->assertHasNoErrors();

    $transaction = DailyTransaction::where('business_unit_id', $this->unit->id)
        ->whereDate('transaction_date', '2026-09-10')
        ->firstOrFail();

    expect((float) $transaction->total_income)->toBe(30_000.0)
        ->and(TransactionItem::where('daily_transaction_id', $transaction->id)->value('subtotal'))
        ->toEqual(30_000);
});

test('a date can only be submitted once', function () {
    $this->actingAs($this->head);

    DailyTransaction::factory()->for($this->unit)->create([
        'transaction_date' => '2026-09-10',
    ]);

    Livewire::test(RecordDailyTransaction::class)
        ->set('transactionDate', '2026-09-10')
        ->set("inputs.{$this->category->id}.quantity", '3')
        ->call('submit')
        ->assertSet('showDuplicateError', true);

    expect(DailyTransaction::where('business_unit_id', $this->unit->id)->count())->toBe(1);
});

test('a submission with no income is rejected', function () {
    $this->actingAs($this->head);

    Livewire::test(RecordDailyTransaction::class)
        ->set('transactionDate', '2026-09-10')
        ->call('submit')
        ->assertHasErrors('totalIncome');

    expect(DailyTransaction::count())->toBe(0);
});

test('non unit heads cannot open the transaction form', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(RecordDailyTransaction::class)->assertForbidden();
});
