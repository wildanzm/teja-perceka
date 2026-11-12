<?php

use App\Livewire\Transactions\JournalTab;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->accounts = createCoreAccounts();
});

test('unit heads only see journals belonging to their own unit', function () {
    $own = BusinessUnit::where('code', 'SC')->firstOrFail();
    $other = BusinessUnit::where('code', 'SB')->firstOrFail();

    JournalEntry::factory()->debit(10_000)->for($own)->create([
        'voucher_number' => 'KSC001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);
    JournalEntry::factory()->debit(20_000)->for($other)->create([
        'voucher_number' => 'KSB001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);

    $this->actingAs(createUserWithRole('kepala_unit', $own));

    $vouchers = Livewire::test(JournalTab::class, [
        'unitId' => $own->id,
        'mode' => 'monthly',
        'month' => '2026-09',
    ])->get('transactions')['groups']
        ->flatten()
        ->pluck('voucher_number')
        ->unique()
        ->values()
        ->all();

    expect($vouchers)->toBe(['KSC001']);
});

test('search filters journals by account code', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    JournalEntry::factory()->debit(10_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);
    JournalEntry::factory()->debit(20_000)->create([
        'voucher_number' => 'KBM002',
        'transaction_date' => '2026-09-11',
        'account_id' => $this->accounts['hpp']->id,
    ]);

    $vouchers = Livewire::test(JournalTab::class, [
        'mode' => 'monthly',
        'month' => '2026-09',
        'search' => $this->accounts['hpp']->code,
    ])->get('transactions')['groups']
        ->flatten()
        ->pluck('voucher_number')
        ->unique()
        ->values()
        ->all();

    expect($vouchers)->toBe(['KBM002']);
});

test('deleting a voucher renumbers the remaining journals in the month', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    JournalEntry::factory()->debit(10_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);
    JournalEntry::factory()->debit(10_000)->create([
        'voucher_number' => 'KBM002',
        'transaction_date' => '2026-09-15',
        'account_id' => $this->accounts['expense']->id,
    ]);

    $first = JournalEntry::where('voucher_number', 'KBM001')->firstOrFail();

    Livewire::test(JournalTab::class, ['mode' => 'monthly', 'month' => '2026-09'])
        ->call('confirmDelete', $first->id)
        ->call('executeDelete')
        ->assertSet('showDeleteModal', false);

    expect(JournalEntry::where('voucher_number', 'KBM001')->count())->toBe(1)
        ->and(JournalEntry::where('voucher_number', 'KBM002')->count())->toBe(0);
});

test('read-only roles cannot delete journals', function () {
    $this->actingAs(createUserWithRole('pengawas'));

    $entry = JournalEntry::factory()->debit(10_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);

    Livewire::test(JournalTab::class, ['mode' => 'monthly', 'month' => '2026-09'])
        ->call('confirmDelete', $entry->id)
        ->call('executeDelete')
        ->assertForbidden();

    expect($entry->fresh())->not->toBeNull();
});
