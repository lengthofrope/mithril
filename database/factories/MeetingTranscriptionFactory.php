<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TranscriptionStatus;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingTranscription>
 */
class MeetingTranscriptionFactory extends Factory
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
            'content' => fake()->paragraphs(3, true),
            'language' => 'nl',
            'provider' => 'whisper',
            'status' => TranscriptionStatus::Completed,
        ];
    }

    /**
     * Set the transcription as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'content' => null,
            'status' => TranscriptionStatus::Pending,
        ]);
    }

    /**
     * Set the transcription as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'content' => null,
            'status' => TranscriptionStatus::Failed,
            'error_message' => 'Transcription service unavailable.',
        ]);
    }
}
