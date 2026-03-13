<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
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
