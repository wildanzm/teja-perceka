<?php

namespace App\Models;

use Database\Factories\DailyTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_unit_id
 * @property int $user_id
 * @property Carbon $transactionDate
 * @property Carbon|null $end_date
 * @property float $total_income
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyTransaction extends Model
{
    /** @use HasFactory<DailyTransactionFactory> */
    use HasFactory;

    protected $table = 'daily_transactions';

    protected $fillable = [
        'business_unit_id',
        'user_id',
        'transaction_date',
        'end_date',
        'total_income',
        'total_expense',
        'notes',
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
            'end_date' => 'date',
            'total_income' => 'float',
            'total_expense' => 'float',
        ];
    }

    /**
     * Get the unit wisata associated with the transaction.
     *
     * @return BelongsTo<BusinessUnit, $this>
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /**
     * Get the user who inputted the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the details of the transaction.
     *
     * @return HasMany<TransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'daily_transaction_id');
    }
}
