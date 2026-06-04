<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
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

test('auto-save successfully renames a task category', function () {
    /** @var \Tests\TestCase $this */
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Original']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => 'Renamed Category',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true, 'message' => 'Saved.']);

    $this->assertDatabaseHas('task_categories', [
        'id' => $category->id,
        'name' => 'Renamed Category',
    ]);
});

test('auto-save successfully renames a task group', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'name' => 'Original Group']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_group',
        'id' => $group->id,
        'field' => 'name',
        'value' => 'Renamed Group',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true, 'message' => 'Saved.']);

    $this->assertDatabaseHas('task_groups', [
        'id' => $group->id,
        'name' => 'Renamed Group',
    ]);
});

test('auto-save validates unique category name per user', function () {
    /** @var \Tests\TestCase $this */
    TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Existing']);
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Other']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => 'Existing',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save allows same category name for different users', function () {
    /** @var \Tests\TestCase $this */
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);
    TaskCategory::factory()->create(['user_id' => $otherUser->id, 'name' => 'Shared Name']);
    $this->actingAs($this->user);
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Original']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => 'Shared Name',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);
});

test('auto-save allows category to keep its own name', function () {
    /** @var \Tests\TestCase $this */
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Keep Me']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => 'Keep Me',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);
});

test('auto-save rejects empty name for task category', function () {
    /** @var \Tests\TestCase $this */
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Valid']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => '',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save rejects empty name for task group', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'name' => 'Valid']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_group',
        'id' => $group->id,
        'field' => 'name',
        'value' => '',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save rejects name exceeding 255 characters for task category', function () {
    /** @var \Tests\TestCase $this */
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Short']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_category',
        'id' => $category->id,
        'field' => 'name',
        'value' => str_repeat('a', 256),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save validates hex color format for task group', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'color' => '#3b82f6']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_group',
        'id' => $group->id,
        'field' => 'color',
        'value' => 'not-a-color',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save updates the title field on a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['user_id' => $this->user->id, 'title' => 'Old title']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'follow_up',
        'id' => $followUp->id,
        'field' => 'title',
        'value' => 'New title',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('follow_ups', [
        'id' => $followUp->id,
        'title' => 'New title',
    ]);
});

test('auto-save updates the description body on a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['user_id' => $this->user->id, 'description' => null]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'follow_up',
        'id' => $followUp->id,
        'field' => 'description',
        'value' => 'A longer body of context.',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('follow_ups', [
        'id' => $followUp->id,
        'description' => 'A longer body of context.',
    ]);
});

test('auto-save updates the priority on a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['user_id' => $this->user->id, 'priority' => Priority::Normal]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'follow_up',
        'id' => $followUp->id,
        'field' => 'priority',
        'value' => Priority::Urgent->value,
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('follow_ups', [
        'id' => $followUp->id,
        'priority' => Priority::Urgent->value,
    ]);
});

test('auto-save rejects an invalid priority on a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['user_id' => $this->user->id]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'follow_up',
        'id' => $followUp->id,
        'field' => 'priority',
        'value' => 'critical',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('auto-save toggles the is_private flag on a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'follow_up',
        'id' => $followUp->id,
        'field' => 'is_private',
        'value' => true,
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('follow_ups', [
        'id' => $followUp->id,
        'is_private' => true,
    ]);
});

test('auto-save successfully updates task group color', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'color' => '#3b82f6']);

    $response = $this->postJson('/api/v1/auto-save', [
        'model' => 'task_group',
        'id' => $group->id,
        'field' => 'color',
        'value' => '#ff5733',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('task_groups', [
        'id' => $group->id,
        'color' => '#ff5733',
    ]);
});
