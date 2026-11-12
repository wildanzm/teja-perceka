<?php

use App\Livewire\Expenses\RecordExpense;
use App\Models\Account;
use App\Models\JournalEntry;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('bendahara'));
});

test('treasurer records a balanced BUMDes expense journal', function () {
    $expense = Account::where('code', '6-0001')->firstOrFail();

    Livewire::test(RecordExpense::class)
        ->set('transactionDate', '2026-09-10')
        ->set('items.0.account_id', $expense->id)
        ->set('items.0.description', 'Spanduk BUMDes')
        ->set('items.0.amount', '120.000')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    $entries = JournalEntry::where('voucher_number', 'KBM001')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->sum('debit'))->toBe(120_000.0)
        ->and($entries->sum('credit'))->toBe(120_000.0);
});

test('expense submission rejects a zero amount', function () {
    $expense = Account::where('code', '6-0001')->firstOrFail();

    Livewire::test(RecordExpense::class)
        ->set('transactionDate', '2026-09-10')
        ->set('items.0.account_id', $expense->id)
        ->set('items.0.description', 'Spanduk BUMDes')
        ->set('items.0.amount', '0')
        ->call('submit')
        ->assertHasErrors('items.0.amount');

    expect(JournalEntry::count())->toBe(0);
});
