<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating CalendarEventLink test instances.
 *
 * @extends Factory<CalendarEventLink>
 */
class CalendarEventLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CalendarEventLink>
     */
    protected $model = CalendarEventLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_event_id' => CalendarEvent::factory(),
            'linkable_type'     => Meeting::class,
            'linkable_id'       => Meeting::factory(),
        ];
    }

    /**
     * Configure the link to point to a specific Meeting.
     *
     * @param Meeting $meeting The meeting to link to.
     * @return static
     */
    public function forMeeting(Meeting $meeting): static
    {
        return $this->state([
            'linkable_type' => Meeting::class,
            'linkable_id'   => $meeting->id,
        ]);
    }

    /**
     * Configure the link to point to a specific Task.
     *
     * @param Task $task The task to link to.
     * @return static
     */
    public function forTask(Task $task): static
    {
        return $this->state([
            'linkable_type' => Task::class,
            'linkable_id'   => $task->id,
        ]);
    }

    /**
     * Configure the link to point to a specific FollowUp.
     *
     * @param FollowUp $followUp The follow-up to link to.
     * @return static
     */
    public function forFollowUp(FollowUp $followUp): static
    {
        return $this->state([
            'linkable_type' => FollowUp::class,
            'linkable_id'   => $followUp->id,
        ]);
    }

    /**
     * Configure the link to point to a specific Note.
     *
     * @param Note $note The note to link to.
     * @return static
     */
    public function forNote(Note $note): static
    {
        return $this->state([
            'linkable_type' => Note::class,
            'linkable_id'   => $note->id,
        ]);
    }
}
