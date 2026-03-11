<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => Priority::Normal,
            'status' => TaskStatus::Open,
            'deadline' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'team_id' => null,
            'team_member_id' => null,
            'task_group_id' => null,
            'task_category_id' => null,
            'is_private' => false,
            'is_recurring' => false,
            'recurrence_interval' => null,
            'recurrence_custom_days' => null,
            'recurrence_series_id' => null,
            'recurrence_parent_id' => null,
        ];
    }
}
