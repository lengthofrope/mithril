<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
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
            'activity_id' => Activity::factory(),
            'filename' => fake()->word() . '.pdf',
            'path' => 'attachments/' . fake()->date('Y/m') . '/' . fake()->uuid() . '.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 10485760),
        ];
    }
}
