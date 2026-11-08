<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_unit_id
 * @property int|null $account_id
 * @property string $name
 * @property CategoryType $type
 * @property TransactionType $direction
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TransactionCategory extends Model
{
    use HasFactory;

    protected $table = 'transaction_categories';

    protected $fillable = [
        'business_unit_id',
        'account_id',
        'name',
        'type',
        'direction',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'direction' => TransactionType::class,
        ];
    }

    /**
     * Get the unit wisata associated with the category.
     *
     * @return BelongsTo<BusinessUnit, $this>
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /**
     * Get the account code associated with the category.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the price history associated with the category.
     *
     * @return HasMany<CategoryPriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(CategoryPriceHistory::class, 'transaction_category_id');
    }

    /**
     * Get the price applicable for a specific date.
     */
    public function priceAt(string|\DateTimeInterface $date): float
    {
        $history = $this->priceHistory()
            ->where('effective_from', '<=', $date)
            ->orderBy('effective_from', 'desc')
            ->first();

        return $history ? (float) $history->price : 0.0;
    }
}
