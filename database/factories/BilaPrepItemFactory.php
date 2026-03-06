<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BilaPrepItem>
 */
class BilaPrepItemFactory extends Factory
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
            'team_member_id' => TeamMember::factory(),
            'bila_id' => null,
            'content' => fake()->sentence(),
            'is_discussed' => false,
        ];
    }
}
