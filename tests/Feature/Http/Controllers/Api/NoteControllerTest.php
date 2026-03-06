<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all notes', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Note::factory()->count(4)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/notes');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(4);
});

test('index returns empty data when no notes exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/notes');

    $response->assertOk()
        ->assertJson(['success' => true, 'data' => []]);
});

test('store creates a new note and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $payload = [
        'title' => 'Meeting notes',
        'content' => 'Discussion points from today.',
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/notes', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.title'))->toBe('Meeting notes');

    $this->assertDatabaseHas('notes', ['title' => 'Meeting notes']);
});

test('store returns 422 when title is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'content' => 'Content without title',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('store returns 422 when title exceeds max length', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => str_repeat('x', 256),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('store returns 422 when team_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => 'Test note',
        'team_id' => 9999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id']);
});

test('store returns 422 when team_member_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => 'Test note',
        'team_member_id' => 9999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id']);
});

test('store creates a pinned note when is_pinned is true', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => 'Pinned note',
        'is_pinned' => true,
    ]);

    $response->assertStatus(201);

    expect($response->json('data.is_pinned'))->toBeTrue();
});

test('store assigns a note to an existing team', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => 'Team note',
        'team_id' => $team->id,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('notes', [
        'title' => 'Team note',
        'team_id' => $team->id,
    ]);
});

test('store assigns a note to an existing team member', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/notes', [
        'title' => 'Member note',
        'team_member_id' => $member->id,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('notes', [
        'title' => 'Member note',
        'team_member_id' => $member->id,
    ]);
});

test('update modifies an existing note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}", [
        'title' => 'New title',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.title'))->toBe('New title');
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}", [
        'title' => 'Updated',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when note does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/notes/9999', [
        'title' => 'Ghost note',
    ]);

    $response->assertNotFound();
});

test('destroy deletes a note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/notes/{$note->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
});

test('destroy returns 404 when note does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson('/api/v1/notes/9999');

    $response->assertNotFound();
});
