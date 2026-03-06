<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FollowUp>
 */
class FollowUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => null,
            'team_member_id' => null,
            'description' => fake()->sentence(),
            'waiting_on' => fake()->optional()->name(),
            'follow_up_date' => fake()->dateTimeBetween('now', '+1 month'),
            'snoozed_until' => null,
            'status' => FollowUpStatus::Open,
        ];
    }
}
