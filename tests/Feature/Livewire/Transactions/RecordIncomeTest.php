<?php

use App\Livewire\Transactions\RecordIncome;
use App\Models\Account;
use App\Models\JournalEntry;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('bendahara'));
    $this->revenue = Account::where('code', '4-2000')->firstOrFail();
});

test('treasurer records BUMDes income as a balanced journal', function () {
    Livewire::test(RecordIncome::class)
        ->set('transactionDate', '2026-09-10')
        ->set('items.0.account_id', $this->revenue->id)
        ->set('items.0.description', 'Pendapatan Tiket')
        ->set('items.0.amount', '150.000')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    $entries = JournalEntry::where('voucher_number', 'DBM001')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->sum('debit'))->toBe(150_000.0)
        ->and($entries->sum('credit'))->toBe(150_000.0);
});

test('the sales-return account cannot be used for income', function () {
    $returnAccount = Account::where('code', '4-4000')->firstOrFail();

    Livewire::test(RecordIncome::class)
        ->set('transactionDate', '2026-09-10')
        ->set('items.0.account_id', $returnAccount->id)
        ->set('items.0.description', 'Retur')
        ->set('items.0.amount', '10.000')
        ->call('submit')
        ->assertHasErrors('items.0.account_id');

    expect(JournalEntry::count())->toBe(0);
});
