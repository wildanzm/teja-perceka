<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $table = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'sort_order',
        'is_header',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_header' => 'boolean',
        ];
    }

    /**
     * Get the transaction categories associated with the account.
     *
     * @return HasMany<TransactionCategory, $this>
     */
    public function transactionCategory(): HasMany
    {
        return $this->hasMany(TransactionCategory::class, 'account_id');
    }
}
