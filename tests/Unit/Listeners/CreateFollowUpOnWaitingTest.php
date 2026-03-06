<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Listeners\CreateFollowUpOnWaiting;
use App\Models\FollowUp;
use App\Models\Task;

test('creates a follow-up when task transitions to waiting status', function () {
    $task = Task::factory()->create(['title' => 'Blocked on review']);

    $event = new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseHas('follow_ups', [
        'task_id' => $task->id,
        'description' => 'Blocked on review',
        'status' => FollowUpStatus::Open->value,
    ]);
});

test('creates follow-up with follow_up_date set to 3 days from now', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $followUp = FollowUp::where('task_id', $task->id)->first();
    expect($followUp)->not->toBeNull();
    expect($followUp->follow_up_date->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('creates follow-up linked to task team member', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseHas('follow_ups', [
        'task_id' => $task->id,
        'team_member_id' => $task->team_member_id,
    ]);
});

test('does not create follow-up when status changes to open', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Done, TaskStatus::Open);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseCount('follow_ups', 0);
});

test('does not create follow-up when status changes to in_progress', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::InProgress);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseCount('follow_ups', 0);
});

test('does not create follow-up when status changes to done', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Waiting, TaskStatus::Done);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseCount('follow_ups', 0);
});

test('does not create duplicate follow-up when active follow-up already exists for task', function () {
    $task = Task::factory()->create();

    FollowUp::factory()->create([
        'task_id' => $task->id,
        'status' => FollowUpStatus::Open,
    ]);

    $event = new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseCount('follow_ups', 1);
});

test('creates follow-up when only a done follow-up exists for the task', function () {
    $task = Task::factory()->create();

    FollowUp::factory()->create([
        'task_id' => $task->id,
        'status' => FollowUpStatus::Done,
    ]);

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $this->assertDatabaseCount('follow_ups', 2);
});

test('follow-up description matches task title', function () {
    $task = Task::factory()->create(['title' => 'Awaiting client feedback']);

    $event = new TaskStatusChanged($task, TaskStatus::InProgress, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $followUp = FollowUp::where('task_id', $task->id)->first();
    expect($followUp->description)->toBe('Awaiting client feedback');
});

test('follow-up waiting_on is null by default', function () {
    $task = Task::factory()->create();

    $event = new TaskStatusChanged($task, TaskStatus::Open, TaskStatus::Waiting);
    (new CreateFollowUpOnWaiting())->handle($event);

    $followUp = FollowUp::where('task_id', $task->id)->first();
    expect($followUp->waiting_on)->toBeNull();
});
