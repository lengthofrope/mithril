<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('auto-save successfully updates a single fillable field on a task', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id, 'title' => 'Original title']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'title',
        'value' => 'Auto-saved title',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Saved.',
        ]);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Auto-saved title',
    ]);
});

test('auto-save response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'title',
        'value' => 'Updated',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
    expect($response->json('saved_at'))->toBeString();
});

test('auto-save returns the updated model in data', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id, 'title' => 'Before']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'title',
        'value' => 'After',
    ]);

    $response->assertOk();

    expect($response->json('data.title'))->toBe('After');
    expect($response->json('data.id'))->toBe($task->id);
});

test('auto-save works with a note model', function () {
    /** @var \Tests\TestCase $this */
    $note = Note::factory()->create(['user_id' => $this->user->id, 'title' => 'Old title']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'note',
        'id' => $note->id,
        'field' => 'title',
        'value' => 'New title',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'title' => 'New title',
    ]);
});

test('auto-save works with a team model', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create(['user_id' => $this->user->id, 'name' => 'Old team name']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'team',
        'id' => $team->id,
        'field' => 'name',
        'value' => 'New team name',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'New team name',
    ]);
});

test('auto-save returns 404 when record does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => 9999,
        'field' => 'title',
        'value' => 'Whatever',
    ]);

    $response->assertNotFound();
});

test('auto-save returns 422 when model key is unknown', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'unknown_model',
        'id' => 1,
        'field' => 'title',
        'value' => 'Test',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Unknown model: unknown_model',
        ]);
});

test('auto-save returns 422 when field is not fillable on the model', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'id',
        'value' => 999,
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($response->json('message'))->toContain("'id' cannot be auto-saved");
});

test('auto-save returns 422 validation error when model field is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/auto-save', [
        'id' => 1,
        'field' => 'title',
        'value' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['model']);
});

test('auto-save returns 422 validation error when id is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'field' => 'title',
        'value' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['id']);
});

test('auto-save returns 422 validation error when field is missing', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'value' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['field']);
});

test('auto-save returns 422 validation error when value key is absent', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'title',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('auto-save accepts null as a valid value for nullable fields', function () {
    /** @var \Tests\TestCase $this */
    $task = Task::factory()->create(['user_id' => $this->user->id, 'description' => 'Some description']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => $task->id,
        'field' => 'description',
        'value' => null,
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'description' => null,
    ]);
});

test('auto-save returns 422 validation error when id is zero', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task',
        'id' => 0,
        'field' => 'title',
        'value' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['id']);
});
