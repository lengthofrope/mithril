<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WeeklyReflection>
 */
class WeeklyReflectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = fake()->dateTimeBetween('-1 year', 'now');
        $weekEnd = (clone $weekStart)->modify('+6 days');

        return [
            'user_id' => \App\Models\User::factory(),
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'summary' => fake()->optional()->paragraph(),
            'reflection' => fake()->optional()->paragraph(),
        ];
    }
}
