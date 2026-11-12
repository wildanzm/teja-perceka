<?php

namespace App\Models;

use Database\Factories\TransactionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $daily_transaction_id
 * @property int $transaction_category_id
 * @property int|null $quantity
 * @property float $unit_price
 * @property float $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TransactionItem extends Model
{
    /** @use HasFactory<TransactionItemFactory> */
    use HasFactory;

    protected $table = 'transaction_items';

    protected $fillable = [
        'daily_transaction_id',
        'transaction_category_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'subtotal' => 'float',
        ];
    }

    /**
     * Get the transaction header associated with this detail.
     *
     * @return BelongsTo<DailyTransaction, $this>
     */
    public function dailyTransaction(): BelongsTo
    {
        return $this->belongsTo(DailyTransaction::class, 'daily_transaction_id');
    }

    /**
     * Get the category associated with this detail.
     *
     * @return BelongsTo<TransactionCategory, $this>
     */
    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
}
