<?php

use App\Livewire\UnitHead\RecordExpense;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();

    $this->unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $this->head = createUserWithRole('kepala_unit', $this->unit);
});

test('unit head records an expense scoped to their own unit', function () {
    $this->actingAs($this->head);
    $expense = Account::where('code', '6-0001')->firstOrFail();

    Livewire::test(RecordExpense::class)
        ->set('transactionDate', '2026-09-10')
        ->set('items.0.account_id', $expense->id)
        ->set('items.0.description', 'Beli ATK')
        ->set('items.0.amount', '75.000')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    $entries = JournalEntry::where('business_unit_id', $this->unit->id)
        ->where('voucher_number', 'KSC001')
        ->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->sum('debit'))->toBe(75_000.0)
        ->and($entries->sum('credit'))->toBe(75_000.0);
});

test('users without a unit cannot open the unit expense form', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(RecordExpense::class)->assertForbidden();
});
