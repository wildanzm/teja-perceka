<?php

namespace Database\Factories;

use App\Models\CategoryPriceHistory;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryPriceHistory>
 */
class CategoryPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_category_id' => TransactionCategory::factory(),
            'price' => fake()->numberBetween(1_000, 1_000_000),
            'effective_from' => now()->startOfYear()->toDateString(),
        ];
    }
}
