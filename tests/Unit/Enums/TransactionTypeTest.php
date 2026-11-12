<?php

use App\Enums\TransactionType;

test('transaction types expose persisted Indonesian values', function () {
    expect(TransactionType::Income->value)->toBe('pemasukan')
        ->and(TransactionType::Expense->value)->toBe('pengeluaran');
});
