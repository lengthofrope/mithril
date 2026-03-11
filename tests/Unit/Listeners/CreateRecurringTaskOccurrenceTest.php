<?php

declare(strict_types=1);

use App\Enums\RecurrenceInterval;
use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Listeners\CreateRecurringTaskOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('creates next occurrence when a recurring task is marked as Done', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Weekly standup prep',
        'is_recurring' => true,
        'recurrence_interval' => RecurrenceInterval::Weekly,
        'deadline' => Carbon::parse('2026-03-14'),
    ]);

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Weekly standup prep',
        'is_recurring' => true,
        'recurrence_parent_id' => $task->id,
        'status' => TaskStatus::Open->value,
    ]);
});

test('does not create occurrence when task is not recurring', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => false,
    ]);

    $initialCount = Task::count();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    expect(Task::count())->toBe($initialCount);
});

test('does not create occurrence when status does not change to Done', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => true,
        'recurrence_interval' => RecurrenceInterval::Weekly,
    ]);

    $initialCount = Task::count();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::InProgress);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    expect(Task::count())->toBe($initialCount);
});

test('does not create occurrence when old status was already Done', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => true,
        'recurrence_interval' => RecurrenceInterval::Weekly,
    ]);

    $initialCount = Task::count();

    $event = new TaskStatusChanged($task, TaskStatus::Done, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    expect(Task::count())->toBe($initialCount);
});

test('does not create occurrence when recurrence_interval is null', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => true,
        'recurrence_interval' => null,
    ]);

    $initialCount = Task::count();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    expect(Task::count())->toBe($initialCount);
});

test('new occurrence inherits recurrence_series_id from completed task', function () {
    $user = User::factory()->create();
    $seriesId = (string) \Illuminate\Support\Str::uuid();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => true,
        'recurrence_interval' => RecurrenceInterval::Daily,
        'recurrence_series_id' => $seriesId,
        'deadline' => Carbon::parse('2026-03-14'),
    ]);

    $event = new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    $this->assertDatabaseHas('tasks', [
        'recurrence_series_id' => $seriesId,
        'recurrence_parent_id' => $task->id,
    ]);
});

test('new occurrence user_id matches the completed task user_id', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'is_recurring' => true,
        'recurrence_interval' => RecurrenceInterval::Monthly,
        'deadline' => Carbon::parse('2026-03-14'),
    ]);

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);
    (new CreateRecurringTaskOccurrence(new RecurrenceService()))->handle($event);

    $this->assertDatabaseHas('tasks', [
        'user_id' => $user->id,
        'recurrence_parent_id' => $task->id,
    ]);
});
