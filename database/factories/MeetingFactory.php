<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
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
            'team_id' => null,
            'title' => fake()->sentence(3),
            'type' => MeetingType::OneOnOne,
            'status' => MeetingStatus::Scheduled,
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'notes' => fake()->optional()->paragraph(),
            'transcription_language' => 'nl',
        ];
    }

    /**
     * Set the meeting as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => MeetingStatus::Completed,
            'is_done' => true,
        ]);
    }

    /**
     * Set the meeting as a team meeting.
     */
    public function teamMeeting(): static
    {
        return $this->state(fn (): array => [
            'type' => MeetingType::Team,
        ]);
    }
}
