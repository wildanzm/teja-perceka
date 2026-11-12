<?php

namespace App\Models;

use Database\Factories\CategoryPriceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transaction_category_id
 * @property float $price
 * @property Carbon $effective_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CategoryPriceHistory extends Model
{
    /** @use HasFactory<CategoryPriceHistoryFactory> */
    use HasFactory;

    protected $table = 'category_price_history';

    protected $fillable = [
        'transaction_category_id',
        'price',
        'effective_from',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'effective_from' => 'date',
        ];
    }

    /**
     * Get the category associated with this price history.
     *
     * @return BelongsTo<TransactionCategory, $this>
     */
    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
}
