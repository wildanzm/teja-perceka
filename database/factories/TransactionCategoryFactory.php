<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionCategory>
 */
class TransactionCategoryFactory extends Factory
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
            'account_id' => Account::factory()->revenue(),
            'name' => fake()->unique()->words(2, true),
            'type' => CategoryType::PriceTimesQuantity,
            'direction' => TransactionType::Income,
        ];
    }
}
