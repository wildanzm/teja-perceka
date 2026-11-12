<?php

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Support\BumdesCashBalance;

beforeEach(function () {
    $this->accounts = createCoreAccounts();
});

test('net unit income excludes deficit units', function () {
    $surplus = BusinessUnit::factory()->create();
    $deficit = BusinessUnit::factory()->create();

    JournalEntry::factory()->credit(100_000)->for($surplus)->create([
        'voucher_number' => 'D'.$surplus->code.'001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['revenue']->id,
    ]);
    JournalEntry::factory()->debit(30_000)->for($surplus)->create([
        'voucher_number' => 'K'.$surplus->code.'001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);
    JournalEntry::factory()->credit(20_000)->for($deficit)->create([
        'voucher_number' => 'D'.$deficit->code.'001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['revenue']->id,
    ]);
    JournalEntry::factory()->debit(50_000)->for($deficit)->create([
        'voucher_number' => 'K'.$deficit->code.'001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['expense']->id,
    ]);

    expect(BumdesCashBalance::getNetIncomePerUnit('2026-09-01', '2026-09-30'))
        ->toHaveCount(1)
        ->and(BumdesCashBalance::getTotalNetUnitIncome('2026-09-01', '2026-09-30'))
        ->toBe(70_000.0);
});

test('opening balance includes manual balances and prior BUMDes journals', function () {
    OpeningBalance::create(['period' => '2026-08', 'amount' => 100_000]);

    JournalEntry::factory()->credit(50_000)->create([
        'voucher_number' => 'DBM001',
        'transaction_date' => '2026-08-15',
        'account_id' => $this->accounts['revenue']->id,
    ]);
    JournalEntry::factory()->debit(40_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-08-20',
        'account_id' => $this->accounts['expense']->id,
    ]);

    expect(BumdesCashBalance::getOpeningBalance('2026-09-01'))->toBe(110_000.0);
});

test('virtual opening entries are empty for non-positive amounts and missing accounts', function () {
    expect(BumdesCashBalance::makeOpeningBalanceEntries('2026-09-01', 0))->toBeEmpty();

    Account::whereIn('code', ['1-1100', '3-2000'])->delete();

    expect(BumdesCashBalance::makeOpeningBalanceEntries('2026-09-01', 1_000))->toBeEmpty();
});

test('unit revenue entries include only profitable units', function () {
    $unit = BusinessUnit::factory()->create(['name' => 'Unit Surplus']);

    JournalEntry::factory()->credit(50_000)->for($unit)->create([
        'voucher_number' => 'DXX001',
        'transaction_date' => '2026-09-10',
        'account_id' => $this->accounts['revenue']->id,
    ]);

    $entries = BumdesCashBalance::makeUnitRevenueEntries('2026-09-01', '2026-09-30');

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->voucher_number)->toBe('DBM002')
        ->and($entries->last()->credit)->toBe(50_000.0);
});
