<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingExtraction>
 */
class MeetingExtractionFactory extends Factory
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
            'type' => fake()->randomElement(ExtractionType::cases()),
            'content' => fake()->sentence(),
            'status' => ExtractionStatus::Pending,
        ];
    }

    /**
     * Set the extraction as accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ExtractionStatus::Accepted,
        ]);
    }
}
