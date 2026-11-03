<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manual cash balance carried into a month from outside the recorded
 * transactions (e.g. the balance of the previous period before the app
 * started). Consumed by BumdesCashBalance::getOpeningBalance().
 *
 * @property int $id
 * @property string $period
 * @property float $amount
 */
class OpeningBalance extends Model
{
    protected $fillable = [
        'period',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}
