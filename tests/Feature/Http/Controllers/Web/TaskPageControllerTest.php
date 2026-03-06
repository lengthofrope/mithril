<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskGroup;
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
    Task::factory()->count(3)->create();

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
    $response->assertViewHas('taskCategories');
    $response->assertViewHas('teams');
    $response->assertViewHas('teamMembers');
    $response->assertViewHas('statuses');
});

test('task index filters tasks by status', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['status' => TaskStatus::Open]);
    Task::factory()->create(['status' => TaskStatus::Done]);
    Task::factory()->create(['status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/tasks?status=' . TaskStatus::Done->value);

    $response->assertOk();
    expect($response->viewData('tasks'))->toHaveCount(2);
});

test('task index filters tasks by priority', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['priority' => Priority::Urgent]);
    Task::factory()->create(['priority' => Priority::Normal]);

    $response = $this->actingAs($user)->get('/tasks?priority=' . Priority::Urgent->value);

    $response->assertOk();
    expect($response->viewData('tasks'))->toHaveCount(1);
});

test('task index groups tasks by task group when group_by_task_group is set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $group = TaskGroup::factory()->create();

    Task::factory()->create(['task_group_id' => $group->id]);
    Task::factory()->create(['task_group_id' => $group->id]);
    Task::factory()->create(['task_group_id' => null]);

    $response = $this->actingAs($user)->get('/tasks?group_by_task_group=1');

    $response->assertOk();
    $tasks = $response->viewData('tasks');
    expect($tasks)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect((bool) $response->viewData('groupByTaskGroup'))->toBeTrue();
});

test('task index does not group tasks when group_by_task_group is not set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->count(3)->create();

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

test('task kanban passes columns keyed by status to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['status' => TaskStatus::Open]);
    Task::factory()->create(['status' => TaskStatus::Open]);
    Task::factory()->create(['status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $response->assertViewHas('columns');
    $columns = $response->viewData('columns');

    expect($columns)->toHaveKey(TaskStatus::Open->value);
    expect($columns)->toHaveKey(TaskStatus::Done->value);
    expect($columns[TaskStatus::Open->value])->toHaveCount(2);
    expect($columns[TaskStatus::Done->value])->toHaveCount(1);
});

test('task kanban columns contain all task status keys', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/tasks/kanban');

    $columns = $response->viewData('columns');
    foreach (TaskStatus::cases() as $status) {
        expect($columns)->toHaveKey($status->value);
    }
});

test('task kanban filters tasks by priority', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
    Task::factory()->create(['priority' => Priority::Normal, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/tasks/kanban?priority=' . Priority::Urgent->value);

    $response->assertOk();
    $openColumn = $response->viewData('columns')[TaskStatus::Open->value];
    expect($openColumn)->toHaveCount(1);
});
