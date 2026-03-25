<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRecording>
 */
class MeetingRecordingFactory extends Factory
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
            'meeting_id' => Meeting::factory(),
            'disk' => 'local',
            'path' => 'recordings/' . fake()->uuid() . '.webm',
            'original_filename' => null,
            'mime_type' => 'audio/webm',
            'size_bytes' => fake()->numberBetween(100000, 50000000),
            'duration_seconds' => fake()->numberBetween(60, 3600),
        ];
    }
}
