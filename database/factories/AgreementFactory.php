<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Agreement>
 */
class AgreementFactory extends Factory
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
            'description' => fake()->sentence(),
            'agreed_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'follow_up_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
