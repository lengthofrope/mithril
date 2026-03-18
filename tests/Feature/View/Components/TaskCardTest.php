<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('task card displays team member name when assigned', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => 'Jane Doe']);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
        'team_member_id' => $member->id,
    ]);
    $task->load('teamMember');

    $view = $this->actingAs($user)->blade(
        '<x-tl.task-card :task="$task" :draggable="false" />',
        ['task' => $task]
    );

    $view->assertSee('Jane Doe');
});

test('task card displays team name when assigned', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Alpha Squad']);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
        'team_id' => $team->id,
    ]);
    $task->load('team');

    $view = $this->actingAs($user)->blade(
        '<x-tl.task-card :task="$task" :draggable="false" />',
        ['task' => $task]
    );

    $view->assertSee('Alpha Squad');
});

test('task card renders inline-select-pill for task group when taskGroups provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $group = TaskGroup::factory()->create(['user_id' => $user->id, 'name' => 'Sprint 1']);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
        'task_group_id' => $group->id,
    ]);
    $task->load('taskGroup');
    $taskGroups = TaskGroup::where('user_id', $user->id)->get();

    $view = $this->actingAs($user)->blade(
        '<x-tl.task-card :task="$task" :draggable="false" :taskGroups="$taskGroups" />',
        ['task' => $task, 'taskGroups' => $taskGroups]
    );

    $view->assertSee('Sprint 1');
    $view->assertSee('task_group_id');
});

test('task card renders None option for task group inline pill', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $group = TaskGroup::factory()->create(['user_id' => $user->id, 'name' => 'Sprint 1']);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
        'task_group_id' => null,
    ]);
    $taskGroups = TaskGroup::where('user_id', $user->id)->get();

    $view = $this->actingAs($user)->blade(
        '<x-tl.task-card :task="$task" :draggable="false" :taskGroups="$taskGroups" />',
        ['task' => $task, 'taskGroups' => $taskGroups]
    );

    $view->assertSee('None');
    $view->assertSee('task_group_id');
});

test('task card does not show member section when no member is assigned', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
        'team_member_id' => null,
        'team_id' => null,
    ]);

    $view = $this->actingAs($user)->blade(
        '<x-tl.task-card :task="$task" :draggable="false" />',
        ['task' => $task]
    );

    $view->assertDontSee('team-member-avatar');
});
