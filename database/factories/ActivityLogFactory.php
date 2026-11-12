<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_type' => 'custom_event',
            'description' => fake()->sentence(4),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'properties' => [],
        ];
    }
}
