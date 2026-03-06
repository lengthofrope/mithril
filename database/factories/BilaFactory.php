<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bila>
 */
class BilaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_member_id' => TeamMember::factory(),
            'scheduled_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
