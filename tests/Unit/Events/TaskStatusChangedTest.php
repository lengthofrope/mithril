<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Models\Task;

test('task status changed event carries the task', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::InProgress);

    expect($event->task)->toBeInstanceOf(Task::class);
    expect($event->task->id)->toBe($task->id);
});

test('task status changed event carries the old status', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);

    expect($event->oldStatus)->toBe(TaskStatus::Open);
});

test('task status changed event carries the new status', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Done);

    expect($event->newStatus)->toBe(TaskStatus::Done);
});

test('task status changed event properties are readonly', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::InProgress);

    expect(fn () => $event->task = Task::factory()->create())->toThrow(Error::class);
    expect(fn () => $event->oldStatus = TaskStatus::Done)->toThrow(Error::class);
    expect(fn () => $event->newStatus = TaskStatus::Open)->toThrow(Error::class);
});

test('task status changed event old and new status can differ across all transitions', function (TaskStatus $old, TaskStatus $new) {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, $old, $new);

    expect($event->oldStatus)->toBe($old);
    expect($event->newStatus)->toBe($new);
})->with([
    [TaskStatus::Open, TaskStatus::InProgress],
    [TaskStatus::InProgress, TaskStatus::Waiting],
    [TaskStatus::Waiting, TaskStatus::Done],
    [TaskStatus::Done, TaskStatus::Open],
]);

test('task status changed event can be constructed with same old and new status', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Open);

    expect($event->oldStatus)->toBe(TaskStatus::Open);
    expect($event->newStatus)->toBe(TaskStatus::Open);
});
