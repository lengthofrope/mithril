<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reorder successfully updates sort_order on tasks', function () {
    /** @var \Tests\TestCase $this */
    $taskA = Task::factory()->create(['sort_order' => 1]);
    $taskB = Task::factory()->create(['sort_order' => 2]);
    $taskC = Task::factory()->create(['sort_order' => 3]);

    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['id' => $taskA->id, 'sort_order' => 3],
            ['id' => $taskB->id, 'sort_order' => 1],
            ['id' => $taskC->id, 'sort_order' => 2],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Reordered successfully.',
        ]);

    $this->assertDatabaseHas('tasks', ['id' => $taskA->id, 'sort_order' => 3]);
    $this->assertDatabaseHas('tasks', ['id' => $taskB->id, 'sort_order' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $taskC->id, 'sort_order' => 2]);
});

test('reorder successfully updates sort_order on teams', function () {
    /** @var \Tests\TestCase $this */
    $teamA = Team::factory()->create(['sort_order' => 1]);
    $teamB = Team::factory()->create(['sort_order' => 2]);

    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'team',
        'items' => [
            ['id' => $teamA->id, 'sort_order' => 2],
            ['id' => $teamB->id, 'sort_order' => 1],
        ],
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('teams', ['id' => $teamA->id, 'sort_order' => 2]);
    $this->assertDatabaseHas('teams', ['id' => $teamB->id, 'sort_order' => 1]);
});

test('reorder returns 422 when model key is unknown', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'unknown_model',
        'items' => [
            ['id' => 1, 'sort_order' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Unknown model: unknown_model',
        ]);
});

test('reorder error response always has success false and null data', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'not_in_map',
        'items' => [
            ['id' => 1, 'sort_order' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'data' => null,
        ]);
});

test('reorder returns 422 validation error when model field is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'items' => [
            ['id' => 1, 'sort_order' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['model']);
});

test('reorder returns 422 validation error when items array is empty', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('reorder returns 422 validation error when items is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('reorder returns 422 validation error when item id is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['sort_order' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.id']);
});

test('reorder returns 422 validation error when item sort_order is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['id' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.sort_order']);
});

test('reorder returns 422 validation error when sort_order is negative', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['id' => 1, 'sort_order' => -1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.sort_order']);
});

test('reorder returns 422 validation error when item id is zero', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['id' => 0, 'sort_order' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.id']);
});

test('reorder response data is null on success', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['sort_order' => 1]);

    $response = $this->postJson('/api/v1/reorder', [
        'model' => 'task',
        'items' => [
            ['id' => $task->id, 'sort_order' => 5],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => null,
        ]);
});
