<?php

namespace Database\Factories;

use App\Models\ProfitAllocationClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitAllocationClosing>
 */
class ProfitAllocationClosingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_mode' => 'monthly',
            'period_key' => now()->format('Y-m'),
            'closing_date' => now()->toDateString(),
            'net_income' => 0,
            'total_outflow' => 0,
            'total_retained' => 0,
            'journal_ids' => [],
            'created_by' => User::factory(),
        ];
    }
}
