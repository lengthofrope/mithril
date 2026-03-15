<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sync replaces all tags on a note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    NoteTag::create(['note_id' => $note->id, 'tag' => 'old-tag', 'user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => ['new-tag', 'another-tag'],
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $tags = $note->fresh()->tags->pluck('tag')->sort()->values()->all();
    expect($tags)->toBe(['another-tag', 'new-tag']);
});

test('sync removes all tags when empty array is sent', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    NoteTag::create(['note_id' => $note->id, 'tag' => 'remove-me', 'user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => [],
    ]);

    $response->assertOk();
    expect($note->fresh()->tags)->toHaveCount(0);
});

test('sync deduplicates tags', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => ['duplicate', 'duplicate', 'unique'],
    ]);

    $tags = $note->fresh()->tags->pluck('tag')->sort()->values()->all();
    expect($tags)->toBe(['duplicate', 'unique']);
});

test('sync trims and lowercases tags', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => ['  Laravel ', 'PHP'],
    ]);

    $tags = $note->fresh()->tags->pluck('tag')->sort()->values()->all();
    expect($tags)->toBe(['laravel', 'php']);
});

test('sync returns 422 when tags is not an array', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => 'not-an-array',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['tags']);
});

test('sync returns 404 for another users note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => ['sneaky'],
    ]);

    $response->assertNotFound();
});

test('sync returns the updated tags in the response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/notes/{$note->id}/tags", [
        'tags' => ['alpha', 'beta'],
    ]);

    $response->assertOk();
    $data = $response->json('data');
    expect(collect($data)->sort()->values()->all())->toBe(['alpha', 'beta']);
});
