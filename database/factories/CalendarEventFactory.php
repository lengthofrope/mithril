<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating CalendarEvent test instances.
 *
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CalendarEvent>
     */
    protected $model = CalendarEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+7 days');
        $end   = (clone $start)->modify('+1 hour');

        return [
            'user_id'            => User::factory(),
            'microsoft_event_id' => fake()->uuid(),
            'subject'            => fake()->sentence(3),
            'start_at'           => $start,
            'end_at'             => $end,
            'is_all_day'         => false,
            'location'           => fake()->optional()->company(),
            'status'             => CalendarEventStatus::Busy,
            'is_online_meeting'  => fake()->boolean(),
            'online_meeting_url' => fake()->optional()->url(),
            'organizer_name'     => fake()->name(),
            'organizer_email'    => fake()->email(),
            'synced_at'          => now(),
        ];
    }
}
