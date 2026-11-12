<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $voucher_number
 * @property Carbon $transactionDate
 * @property string $description
 * @property int $account_id
 * @property float $debit
 * @property float $credit
 * @property int|null $daily_transaction_id
 * @property int $business_unit_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $table = 'journal_entries';

    protected $fillable = [
        'voucher_number',
        'transaction_date',
        'description',
        'account_id',
        'debit',
        'credit',
        'daily_transaction_id',
        'business_unit_id',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'float',
            'credit' => 'float',
        ];
    }

    /**
     * Get the account associated with the journal entry.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the transaction associated with the journal entry.
     *
     * @return BelongsTo<DailyTransaction, $this>
     */
    public function dailyTransaction(): BelongsTo
    {
        return $this->belongsTo(DailyTransaction::class, 'daily_transaction_id');
    }

    /**
     * Get the unit wisata associated with the journal entry.
     *
     * @return BelongsTo<BusinessUnit, $this>
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }
}
