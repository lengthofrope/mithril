<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;

describe('HasResourceLinks trait', function (): void {
    it('deletes CalendarEventLinks when a task is deleted', function (): void {
        $user  = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forTask($task)->create([
            'calendar_event_id' => $event->id,
        ]);

        expect(CalendarEventLink::count())->toBe(1);

        $task->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('deletes CalendarEventLinks when a follow-up is deleted', function (): void {
        $user     = User::factory()->create();
        $event    = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forFollowUp($followUp)->create([
            'calendar_event_id' => $event->id,
        ]);

        expect(CalendarEventLink::count())->toBe(1);

        $followUp->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('deletes CalendarEventLinks when a note is deleted', function (): void {
        $user  = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $note  = Note::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forNote($note)->create([
            'calendar_event_id' => $event->id,
        ]);

        expect(CalendarEventLink::count())->toBe(1);

        $note->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('deletes CalendarEventLinks when a bila is deleted', function (): void {
        $user  = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $bila  = Bila::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forBila($bila)->create([
            'calendar_event_id' => $event->id,
        ]);

        expect(CalendarEventLink::count())->toBe(1);

        $bila->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('does not error when deleting a resource with no links', function (): void {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        expect(CalendarEventLink::count())->toBe(0);

        $task->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('deletes multiple CalendarEventLinks when a resource is deleted', function (): void {
        $user   = User::factory()->create();
        $event1 = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $event2 = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $task   = Task::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forTask($task)->create(['calendar_event_id' => $event1->id]);
        CalendarEventLink::factory()->forTask($task)->create(['calendar_event_id' => $event2->id]);

        expect(CalendarEventLink::count())->toBe(2);

        $task->delete();

        expect(CalendarEventLink::count())->toBe(0);
    });

    it('only deletes links for the deleted resource, not other resources', function (): void {
        $user  = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
        $task1 = Task::factory()->create(['user_id' => $user->id]);
        $task2 = Task::factory()->create(['user_id' => $user->id]);

        CalendarEventLink::factory()->forTask($task1)->create(['calendar_event_id' => $event->id]);
        CalendarEventLink::factory()->forTask($task2)->create(['calendar_event_id' => $event->id]);

        expect(CalendarEventLink::count())->toBe(2);

        $task1->delete();

        expect(CalendarEventLink::count())->toBe(1);
        expect(CalendarEventLink::first()->linkable_id)->toBe($task2->id);
    });
});
