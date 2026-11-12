<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit' => 'unit',
            'price' => fake()->numberBetween(100_000, 10_000_000),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
