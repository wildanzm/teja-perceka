<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTransaction>
 */
class DailyTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'user_id' => User::factory(),
            'transaction_date' => now()->toDateString(),
            'end_date' => null,
            'total_income' => 0,
            'notes' => null,
        ];
    }
}
