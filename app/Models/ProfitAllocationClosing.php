<?php

namespace App\Models;

use Database\Factories\ProfitAllocationClosingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitAllocationClosing extends Model
{
    /** @use HasFactory<ProfitAllocationClosingFactory> */
    use HasFactory;

    protected $table = 'profit_allocation_closings';

    protected $fillable = [
        'period_mode',
        'period_key',
        'closing_date',
        'net_income',
        'total_outflow',
        'total_retained',
        'journal_ids',
        'created_by',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'net_income' => 'decimal:2',
        'total_outflow' => 'decimal:2',
        'total_retained' => 'decimal:2',
        'journal_ids' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
