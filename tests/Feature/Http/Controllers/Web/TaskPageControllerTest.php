<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('task index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks');

    $response->assertOk();
});

test('task index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/tasks');

    $response->assertRedirect('/login');
});

test('task index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks');

    $response->assertViewIs('pages.tasks.index');
});

test('task index passes tasks to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/tasks');

    $response->assertViewHas('tasks');
    expect($response->viewData('tasks'))->toHaveCount(3);
});

test('task index passes required view variables', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks');

    $response->assertViewHas('tasks');
    $response->assertViewHas('filters');
    $response->assertViewHas('taskGroups');
    $response->assertViewHas('statuses');
    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
    $response->assertViewHas('categoryOptions');
    $response->assertViewHas('groupOptions');
});

test('task index filters tasks by status', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/tasks?status=' . TaskStatus::Done->value);

    $response->assertOk();
    expect($response->viewData('tasks'))->toHaveCount(2);
});

test('task index filters tasks by priority', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal]);

    $response = $this->actingAs($user)->get('/tasks?priority=' . Priority::Urgent->value);

    $response->assertOk();
    expect($response->viewData('tasks'))->toHaveCount(1);
});

test('task index groups tasks by task group when group_by_task_group is set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $group = TaskGroup::factory()->create(['user_id' => $user->id]);

    Task::factory()->create(['user_id' => $user->id, 'task_group_id' => $group->id]);
    Task::factory()->create(['user_id' => $user->id, 'task_group_id' => $group->id]);
    Task::factory()->create(['user_id' => $user->id, 'task_group_id' => null]);

    $response = $this->actingAs($user)->get('/tasks?group_by_task_group=1');

    $response->assertOk();
    $tasks = $response->viewData('tasks');
    expect($tasks)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect((bool) $response->viewData('groupByTaskGroup'))->toBeTrue();
});

test('task index does not group tasks when group_by_task_group is not set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/tasks');

    expect($response->viewData('groupByTaskGroup'))->toBeFalse();
});

test('task kanban returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $response->assertOk();
});

test('task kanban redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/tasks/kanban');

    $response->assertRedirect('/login');
});

test('task kanban renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $response->assertViewIs('pages.tasks.kanban');
});

test('task kanban passes tasks statuses filters teamOptions memberOptions to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $response->assertViewHas('tasks');
    $response->assertViewHas('statuses');
    $response->assertViewHas('filters');
    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
});

test('task kanban passes all tasks to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/tasks/kanban');

    expect($response->viewData('tasks'))->toHaveCount(3);
});

test('task kanban passes all TaskStatus cases in statuses', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $statuses = $response->viewData('statuses');
    expect($statuses)->toHaveCount(count(TaskStatus::cases()));
});

test('task kanban filters tasks by priority', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/tasks/kanban?priority=' . Priority::Urgent->value);

    $response->assertOk();
    expect($response->viewData('tasks'))->toHaveCount(1);
});

test('task store creates a new task and redirects to show page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/tasks')
        ->post('/tasks', [
            'title' => 'Test task from form',
            'priority' => 'normal',
        ]);

    $task = Task::where('user_id', $user->id)->where('title', 'Test task from form')->first();
    $response->assertRedirect(route('tasks.show', $task));
    $this->assertDatabaseHas('tasks', [
        'user_id' => $user->id,
        'title' => 'Test task from form',
        'priority' => 'normal',
    ]);
});

test('task store requires a title', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/tasks')
        ->post('/tasks', [
            'priority' => 'normal',
        ]);

    $response->assertRedirect('/tasks');
    $response->assertSessionHasErrors('title');
});

test('task store accepts optional task_group_id', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $group = TaskGroup::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->from('/tasks')
        ->post('/tasks', [
            'title' => 'Grouped task',
            'priority' => 'high',
            'task_group_id' => $group->id,
        ]);

    $task = Task::where('title', 'Grouped task')->first();
    $response->assertRedirect(route('tasks.show', $task));
    $this->assertDatabaseHas('tasks', [
        'title' => 'Grouped task',
        'task_group_id' => $group->id,
    ]);
});

test('task store redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post('/tasks', ['title' => 'Test']);

    $response->assertRedirect('/login');
});

test('task index returns only the partial for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/tasks?status=open', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk();
    $response->assertDontSee('<!DOCTYPE html');
});

test('convert to follow-up creates follow-up linked to task and marks task done', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Review PR feedback',
        'team_member_id' => $member->id,
        'deadline' => '2026-04-20',
        'status' => TaskStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->post("/tasks/{$task->id}/convert-to-follow-up");

    $followUp = FollowUp::where('description', 'Review PR feedback')->first();
    expect($followUp)->not->toBeNull();
    expect($followUp->task_id)->toBe($task->id);
    expect($followUp->team_member_id)->toBe($member->id);
    expect($followUp->follow_up_date->toDateString())->toBe('2026-04-20');
    expect($followUp->status)->toBe(FollowUpStatus::Open);
    expect($task->fresh()->status)->toBe(TaskStatus::Done);
    $response->assertRedirect(route('follow-ups.show', $followUp));
});

test('convert to follow-up returns JSON with follow-up URL for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'AJAX convert test',
        'status' => TaskStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/tasks/{$task->id}/convert-to-follow-up");

    $followUp = FollowUp::where('description', 'AJAX convert test')->first();
    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => ['follow_up_url' => route('follow-ups.show', $followUp)],
        ]);
    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

test('convert to follow-up defaults follow_up_date to today when task has no deadline', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'No deadline task',
        'deadline' => null,
        'status' => TaskStatus::Open,
    ]);

    $this->actingAs($user)
        ->post("/tasks/{$task->id}/convert-to-follow-up");

    $followUp = FollowUp::where('description', 'No deadline task')->first();
    expect($followUp->follow_up_date->toDateString())->toBe(now()->toDateString());
});

test('create follow-up from task creates linked follow-up without changing task status', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Ongoing task',
        'team_member_id' => $member->id,
        'deadline' => '2026-05-01',
        'status' => TaskStatus::InProgress,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/tasks/{$task->id}/create-follow-up");

    $followUp = FollowUp::where('task_id', $task->id)->first();
    expect($followUp)->not->toBeNull();
    expect($followUp->description)->toBe('Ongoing task');
    expect($followUp->team_member_id)->toBe($member->id);
    expect($followUp->follow_up_date->toDateString())->toBe('2026-05-01');
    expect($followUp->status)->toBe(FollowUpStatus::Open);
    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => ['follow_up_url' => route('follow-ups.show', $followUp)],
        ]);
});

test('create follow-up from task defaults follow_up_date to today when no deadline', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'No deadline',
        'deadline' => null,
    ]);

    $this->actingAs($user)
        ->postJson("/tasks/{$task->id}/create-follow-up");

    $followUp = FollowUp::where('task_id', $task->id)->first();
    expect($followUp->follow_up_date->toDateString())->toBe(now()->toDateString());
});

test('create follow-up from task redirects for non-AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Form redirect test',
    ]);

    $response = $this->actingAs($user)
        ->post("/tasks/{$task->id}/create-follow-up");

    $followUp = FollowUp::where('task_id', $task->id)->first();
    $response->assertRedirect(route('follow-ups.show', $followUp));
    expect($task->fresh()->status)->toBe(TaskStatus::Open);
});

test('task show displays linked follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);
    FollowUp::factory()->create([
        'user_id' => $user->id,
        'task_id' => $task->id,
        'description' => 'Linked follow-up',
    ]);

    $response = $this->actingAs($user)->get("/tasks/{$task->id}");

    $response->assertOk();
    $response->assertSee('Linked follow-up');
});
