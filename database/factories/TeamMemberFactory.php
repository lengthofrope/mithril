<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'role' => fake()->optional()->jobTitle(),
            'email' => fake()->optional()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
            'status' => MemberStatus::Available,
            'avatar_path' => null,
            'bila_interval_days' => 14,
            'next_bila_date' => null,
        ];
    }
}
