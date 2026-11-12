<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_number' => fake()->unique()->numerify('GEN###'),
            'transaction_date' => now()->toDateString(),
            'description' => fake()->sentence(3),
            'account_id' => Account::factory(),
            'debit' => 0,
            'credit' => 0,
            'daily_transaction_id' => null,
            'business_unit_id' => null,
        ];
    }

    /**
     * Indicate that the entry is a debit.
     */
    public function debit(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    /**
     * Indicate that the entry is a credit.
     */
    public function credit(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }
}
