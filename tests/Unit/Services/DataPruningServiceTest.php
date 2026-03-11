<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AnalyticsSnapshot;
use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\DataPruningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['prune_after_days' => 60]);
    $this->service = new DataPruningService();
});

test('done tasks older than cutoff are deleted', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(1);
    $this->assertDatabaseCount('tasks', 0);
});

test('done tasks newer than cutoff are preserved', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(30),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(0);
    $this->assertDatabaseCount('tasks', 1);
});

test('open tasks are never deleted regardless of age', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Open,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(0);
    $this->assertDatabaseCount('tasks', 1);
});

test('in-progress tasks are never deleted regardless of age', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::InProgress,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(0);
    $this->assertDatabaseCount('tasks', 1);
});

test('waiting tasks are never deleted regardless of age', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Waiting,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(0);
    $this->assertDatabaseCount('tasks', 1);
});

test('done follow-ups older than cutoff are deleted', function () {
    FollowUp::factory()->create([
        'user_id' => $this->user->id,
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->followUpsDeleted)->toBe(1);
    $this->assertDatabaseCount('follow_ups', 0);
});

test('active follow-ups are never deleted regardless of age', function () {
    FollowUp::factory()->create([
        'user_id' => $this->user->id,
        'status' => FollowUpStatus::Open,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->followUpsDeleted)->toBe(0);
    $this->assertDatabaseCount('follow_ups', 1);
});

test('analytics snapshots are never deleted', function () {
    AnalyticsSnapshot::factory()->create([
        'user_id' => $this->user->id,
        'snapshot_date' => now()->subDays(90),
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('analytics_snapshots', 1);
});

test('bilas are never pruned by retention period', function () {
    $member = TeamMember::factory()->create(['user_id' => $this->user->id]);
    Bila::factory()->create([
        'user_id' => $this->user->id,
        'team_member_id' => $member->id,
        'updated_at' => now()->subDays(90),
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('bilas', 1);
});

test('agreements are never pruned by retention period', function () {
    $member = TeamMember::factory()->create(['user_id' => $this->user->id]);
    Agreement::factory()->create([
        'user_id' => $this->user->id,
        'team_member_id' => $member->id,
        'updated_at' => now()->subDays(90),
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('agreements', 1);
});

test('notes are never pruned by retention period', function () {
    Note::factory()->create([
        'user_id' => $this->user->id,
        'updated_at' => now()->subDays(90),
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('notes', 1);
});

test('orphaned calendar event links are cleaned up', function () {
    $event = CalendarEvent::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);
    CalendarEventLink::factory()->create([
        'calendar_event_id' => $event->id,
        'linkable_type' => Task::class,
        'linkable_id' => $task->id,
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('calendar_event_links', 0);
});

test('task done exactly at cutoff boundary is not pruned', function () {
    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(60),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->tasksDeleted)->toBe(0);
    $this->assertDatabaseCount('tasks', 1);
});

test('tasks from another user are not pruned', function () {
    $otherUser = User::factory()->create(['prune_after_days' => 60]);
    Task::factory()->create([
        'user_id' => $otherUser->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('tasks', 1);
});
