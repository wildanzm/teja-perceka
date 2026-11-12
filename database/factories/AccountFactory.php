<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('#-####'),
            'name' => fake()->unique()->words(2, true),
            'type' => 'aktiva',
            'sort_order' => null,
            'is_header' => false,
        ];
    }

    /**
     * Indicate that the account is a non-posting header row.
     */
    public function header(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'header',
            'is_header' => true,
        ]);
    }

    /**
     * Indicate that the account is a revenue account.
     */
    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pendapatan',
        ]);
    }

    /**
     * Indicate that the account is an expense account.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'beban',
        ]);
    }

    /**
     * Indicate that the account is a liability account.
     */
    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'kewajiban',
        ]);
    }

    /**
     * Indicate that the account is an equity account.
     */
    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ekuitas',
        ]);
    }
}
