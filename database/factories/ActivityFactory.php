<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
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
            'activityable_type' => Task::class,
            'activityable_id' => Task::factory(),
            'type' => ActivityType::Comment,
            'body' => fake()->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * State for a link activity with url and title metadata.
     *
     * @return static
     */
    public function link(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Link,
            'body' => null,
            'metadata' => [
                'url' => fake()->url(),
                'title' => fake()->sentence(4),
            ],
        ]);
    }

    /**
     * State for a system activity with action and changes metadata.
     *
     * @return static
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::System,
            'body' => null,
            'metadata' => [
                'action' => fake()->word(),
                'changes' => [],
            ],
        ]);
    }

    /**
     * State for an attachment activity.
     *
     * @return static
     */
    public function attachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Attachment,
            'body' => null,
            'metadata' => null,
        ]);
    }
}
