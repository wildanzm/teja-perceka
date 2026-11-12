<?php

namespace App\Models;

use Database\Factories\BusinessUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $input_frequency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BusinessUnit extends Model
{
    /** @use HasFactory<BusinessUnitFactory> */
    use HasFactory;

    protected $table = 'business_units';

    protected $fillable = [
        'name',
        'code',
        'input_frequency',
    ];

    /**
     * Get the users associated with the unit.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'business_unit_id');
    }

    /**
     * Get the transaction categories for the unit.
     *
     * @return HasMany<TransactionCategory, $this>
     */
    public function transactionCategory(): HasMany
    {
        return $this->hasMany(TransactionCategory::class, 'business_unit_id');
    }
}
