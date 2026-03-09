<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns a successful response with success flag and data array', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/tasks');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data',
        ])
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(3);
});

test('index returns empty data array when no tasks exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/tasks');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [],
        ]);
});

test('index returns tasks ordered by sort_order', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->create(['user_id' => $user->id, 'title' => 'Third', 'sort_order' => 3]);
    Task::factory()->create(['user_id' => $user->id, 'title' => 'First', 'sort_order' => 1]);
    Task::factory()->create(['user_id' => $user->id, 'title' => 'Second', 'sort_order' => 2]);

    $response = $this->actingAs($user)->getJson('/api/v1/tasks');

    $response->assertOk();

    $titles = array_column($response->json('data'), 'title');
    expect($titles)->toBe(['First', 'Second', 'Third']);
});

test('store creates a new task and returns 201 with the created resource', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $payload = [
        'title' => 'Finish the report',
        'priority' => Priority::High->value,
        'status' => TaskStatus::Open->value,
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data',
            'message',
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.title'))->toBe('Finish the report');

    $this->assertDatabaseHas('tasks', ['title' => 'Finish the report']);
});

test('store returns 422 with validation errors when title is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', [
        'priority' => Priority::High->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('store returns 422 when priority is not a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Test task',
        'priority' => 'critical',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

test('store returns 422 when status is not a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', [
        'title' => 'Test task',
        'status' => 'unknown_status',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('store creates a task with all optional fields', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $payload = [
        'title' => 'Full task',
        'description' => 'A detailed description.',
        'priority' => Priority::Low->value,
        'status' => TaskStatus::InProgress->value,
        'is_private' => true,
        'sort_order' => 5,
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/tasks', $payload);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Full task',
        'is_private' => true,
        'sort_order' => 5,
    ]);
});

test('update modifies an existing task and returns the updated resource', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Original title']);

    $response = $this->actingAs($user)->putJson("/api/v1/tasks/{$task->id}", [
        'title' => 'Updated title',
        'priority' => Priority::Urgent->value,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Updated successfully.',
        ]);

    expect($response->json('data.title'))->toBe('Updated title');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated title',
    ]);
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/tasks/{$task->id}", [
        'title' => 'Updated title',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when task does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/tasks/9999', [
        'title' => 'Does not matter',
    ]);

    $response->assertNotFound();
});

test('destroy deletes a task and returns success message', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/tasks/{$task->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

test('destroy returns 404 when task does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson('/api/v1/tasks/9999');

    $response->assertNotFound();
});

test('patch updates a single FK field sent as string from auto-save', function (string $field, string $factoryClass) {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);
    $related = $factoryClass::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/v1/tasks/{$task->id}", [
        $field => (string) $related->id,
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        $field => $related->id,
    ]);
})->with([
    'team_id' => ['team_id', \App\Models\Team::class],
    'team_member_id' => ['team_member_id', \App\Models\TeamMember::class],
    'task_category_id' => ['task_category_id', \App\Models\TaskCategory::class],
    'task_group_id' => ['task_group_id', \App\Models\TaskGroup::class],
]);

test('patch clears a FK field when empty string is sent from auto-save', function (string $field) {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/v1/tasks/{$task->id}", [
        $field => '',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        $field => null,
    ]);
})->with(['team_id', 'team_member_id', 'task_category_id', 'task_group_id']);
