<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Task show page passes options ---

test('task show passes team and member options to view', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $task = Task::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/tasks/{$task->id}");

    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
    $response->assertViewHas('categoryOptions');
    $response->assertViewHas('groupOptions');
});

test('task show passes priority and status options to view', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/tasks/{$task->id}");

    $response->assertViewHas('priorityOptions');
    $response->assertViewHas('statusOptions');
});

// --- Quick-add form accepts team and member ---

test('task store accepts optional team_id and team_member_id', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)
        ->from('/tasks')
        ->post('/tasks', [
            'title' => 'Assigned task',
            'priority' => 'normal',
            'team_id' => $team->id,
            'team_member_id' => $member->id,
        ]);

    $task = Task::where('title', 'Assigned task')->first();
    $response->assertRedirect(route('tasks.show', $task));
    $this->assertDatabaseHas('tasks', [
        'title' => 'Assigned task',
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);
});

test('task store accepts optional category and deadline', function () {
    $user = User::factory()->create();
    $category = TaskCategory::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->from('/tasks')
        ->post('/tasks', [
            'title' => 'Categorised task',
            'priority' => 'high',
            'task_category_id' => $category->id,
            'deadline' => '2026-04-01',
        ]);

    $task = Task::where('title', 'Categorised task')->first();
    $response->assertRedirect(route('tasks.show', $task));
    $this->assertDatabaseHas('tasks', [
        'title' => 'Categorised task',
        'task_category_id' => $category->id,
    ]);
    $task = Task::where('title', 'Categorised task')->first();
    expect($task->deadline->format('Y-m-d'))->toBe('2026-04-01');
});
