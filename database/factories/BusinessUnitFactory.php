<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::upper(Str::substr(Str::slug($name), 0, 6)).fake()->unique()->randomNumber(3),
            'input_frequency' => 'daily',
        ];
    }

    /**
     * Indicate that the unit reports on a weekly basis.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'input_frequency' => 'weekly',
        ]);
    }
}
