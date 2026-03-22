<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PrepItemType;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingPrepItem>
 */
class MeetingPrepItemFactory extends Factory
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
            'team_member_id' => null,
            'content' => fake()->sentence(),
            'type' => PrepItemType::AgendaItem,
            'is_discussed' => false,
        ];
    }
}
