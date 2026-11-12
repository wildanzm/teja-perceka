<?php

namespace Database\Factories;

use App\Models\ProfitAllocationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitAllocationRecord>
 */
class ProfitAllocationRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->words(2, true),
            'percentage' => 10,
            'allocation_group' => 'pengurang',
            'calc_type' => 'percentage',
            'amount' => 0,
            'flow_type' => 'outflow',
            'account_id' => null,
            'effective_from' => now()->startOfYear()->toDateString(),
            'period_mode' => null,
            'period_key' => null,
            'business_unit_id' => null,
        ];
    }
}
