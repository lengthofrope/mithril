<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;

it('can create a calendar event link', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::create([
        'calendar_event_id' => $event->id,
        'linkable_type'     => Bila::class,
        'linkable_id'       => $bila->id,
    ]);

    expect($link)->toBeInstanceOf(CalendarEventLink::class)
        ->and($link->calendar_event_id)->toBe($event->id)
        ->and($link->linkable_type)->toBe(Bila::class)
        ->and($link->linkable_id)->toBe($bila->id);
});

it('belongs to a calendar event', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::factory()->forBila($bila)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect($link->calendarEvent)->toBeInstanceOf(CalendarEvent::class)
        ->and($link->calendarEvent->id)->toBe($event->id);
});

it('morphs to a bila', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::factory()->forBila($bila)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect($link->linkable)->toBeInstanceOf(Bila::class)
        ->and($link->linkable->id)->toBe($bila->id);
});

it('morphs to a task', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $task  = Task::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::factory()->forTask($task)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect($link->linkable)->toBeInstanceOf(Task::class)
        ->and($link->linkable->id)->toBe($task->id);
});

it('morphs to a follow up', function (): void {
    $user     = User::factory()->create();
    $event    = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::factory()->forFollowUp($followUp)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect($link->linkable)->toBeInstanceOf(FollowUp::class)
        ->and($link->linkable->id)->toBe($followUp->id);
});

it('morphs to a note', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $note  = Note::factory()->create(['user_id' => $user->id]);

    $link = CalendarEventLink::factory()->forNote($note)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect($link->linkable)->toBeInstanceOf(Note::class)
        ->and($link->linkable->id)->toBe($note->id);
});

it('cascades delete when calendar event is deleted', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forBila($bila)->create([
        'calendar_event_id' => $event->id,
    ]);

    expect(CalendarEventLink::count())->toBe(1);

    $event->delete();

    expect(CalendarEventLink::count())->toBe(0);
});

it('prevents duplicate links with unique constraint', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::create([
        'calendar_event_id' => $event->id,
        'linkable_type'     => Bila::class,
        'linkable_id'       => $bila->id,
    ]);

    expect(fn () => CalendarEventLink::create([
        'calendar_event_id' => $event->id,
        'linkable_type'     => Bila::class,
        'linkable_id'       => $bila->id,
    ]))->toThrow(QueryException::class);
});

it('calendar event has links relationship', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);
    $task  = Task::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forBila($bila)->create(['calendar_event_id' => $event->id]);
    CalendarEventLink::factory()->forTask($task)->create(['calendar_event_id' => $event->id]);

    expect($event->links)->toHaveCount(2);
});

it('calendar event has linkedBilas relationship', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forBila($bila)->create(['calendar_event_id' => $event->id]);

    expect($event->linkedBilas)->toHaveCount(1)
        ->and($event->linkedBilas->first())->toBeInstanceOf(Bila::class)
        ->and($event->linkedBilas->first()->id)->toBe($bila->id);
});

it('calendar event stores and retrieves attendees json', function (): void {
    $attendees = [
        ['email' => 'alice@example.com', 'name' => 'Alice'],
        ['email' => 'bob@example.com', 'name' => 'Bob'],
    ];

    $event = CalendarEvent::factory()->create([
        'attendees' => $attendees,
    ]);

    $fresh = CalendarEvent::find($event->id);

    expect($fresh->attendees)->toBeArray()
        ->and($fresh->attendees)->toHaveCount(2)
        ->and($fresh->attendees[0]['email'])->toBe('alice@example.com');
});

it('bila has calendarEventLinks relationship', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $bila  = Bila::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forBila($bila)->create(['calendar_event_id' => $event->id]);

    expect($bila->calendarEventLinks)->toHaveCount(1)
        ->and($bila->calendarEventLinks->first())->toBeInstanceOf(CalendarEventLink::class);
});

it('task has calendarEventLinks relationship', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $task  = Task::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forTask($task)->create(['calendar_event_id' => $event->id]);

    expect($task->calendarEventLinks)->toHaveCount(1)
        ->and($task->calendarEventLinks->first())->toBeInstanceOf(CalendarEventLink::class);
});

it('follow up has calendarEventLinks relationship', function (): void {
    $user     = User::factory()->create();
    $event    = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forFollowUp($followUp)->create(['calendar_event_id' => $event->id]);

    expect($followUp->calendarEventLinks)->toHaveCount(1)
        ->and($followUp->calendarEventLinks->first())->toBeInstanceOf(CalendarEventLink::class);
});

it('note has calendarEventLinks relationship', function (): void {
    $user  = User::factory()->create();
    $event = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $note  = Note::factory()->create(['user_id' => $user->id]);

    CalendarEventLink::factory()->forNote($note)->create(['calendar_event_id' => $event->id]);

    expect($note->calendarEventLinks)->toHaveCount(1)
        ->and($note->calendarEventLinks->first())->toBeInstanceOf(CalendarEventLink::class);
});
