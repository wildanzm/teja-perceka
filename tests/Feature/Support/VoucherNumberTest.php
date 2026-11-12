<?php

use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Support\VoucherNumber;

beforeEach(function () {
    $this->accounts = createCoreAccounts();
});

test('voucher prefixes are extracted only from standard values', function () {
    expect(VoucherNumber::prefixOf('DSB003'))->toBe('DSB')
        ->and(VoucherNumber::prefixOf('ADJ-001'))->toBeNull();
});

test('voucher batches reject non-positive counts', function () {
    expect(fn () => VoucherNumber::nextBatch('KBM', '2026-09-10', 0))
        ->toThrow(InvalidArgumentException::class, 'Batch voucher minimal 1.');
});

test('voucher insertion shifts later entries in chronological order', function () {
    $unit = BusinessUnit::factory()->create();

    $first = VoucherNumber::next('DSB', '2026-09-10', $unit->id);

    JournalEntry::factory()->debit(10_000)->for($unit)->create([
        'voucher_number' => $first['number'],
        'transaction_date' => '2026-09-10',
        'description' => 'Pemasukan',
        'account_id' => $this->accounts['cash']->id,
    ]);

    expect(VoucherNumber::next('DSB', '2026-09-05', $unit->id)['number'])->toBe('DSB001')
        ->and(JournalEntry::where('description', 'Pemasukan')->value('voucher_number'))->toBe('DSB002');
});

test('dbm batches reserve the opening balance voucher', function () {
    OpeningBalance::create(['period' => '2026-08', 'amount' => 500_000]);

    expect(VoucherNumber::nextBatch('DBM', '2026-09-05', 2))->toBe(['DBM002', 'DBM003']);
});

test('voucher batches reject a number shared by distinct transactions', function () {
    foreach (['Transaksi A', 'Transaksi B'] as $description) {
        JournalEntry::factory()->debit(10_000)->create([
            'voucher_number' => 'KBM001',
            'transaction_date' => '2026-09-01',
            'description' => $description,
            'account_id' => $this->accounts['expense']->id,
        ]);
    }

    expect(fn () => VoucherNumber::nextBatch('KBM', '2026-09-05', 1))
        ->toThrow(RuntimeException::class, 'Duplicate voucher KBM001 shared by several transactions.');
});

test('renumberScope closes gaps without changing chronological order', function () {
    $first = JournalEntry::factory()->debit(15_000)->create([
        'voucher_number' => 'KBM001',
        'transaction_date' => '2026-09-02',
        'account_id' => $this->accounts['expense']->id,
    ]);
    $later = JournalEntry::factory()->debit(50_000)->create([
        'voucher_number' => 'KBM005',
        'transaction_date' => '2026-09-12',
        'account_id' => $this->accounts['expense']->id,
    ]);

    VoucherNumber::renumberScope('KBM', '2026-09');

    expect($first->fresh()->voucher_number)->toBe('KBM001')
        ->and($later->fresh()->voucher_number)->toBe('KBM002');
});
