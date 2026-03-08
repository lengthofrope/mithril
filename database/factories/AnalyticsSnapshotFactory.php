<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AnalyticsSnapshot>
 */
class AnalyticsSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'       => \App\Models\User::factory(),
            'snapshot_date' => today(),
            'metric'        => 'tasks_status_open',
            'value'         => fake()->numberBetween(0, 50),
        ];
    }
}
