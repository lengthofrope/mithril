<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notes index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes');

    $response->assertOk();
});

test('notes index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/notes');

    $response->assertRedirect('/login');
});

test('notes index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes');

    $response->assertViewIs('pages.notes.index');
});

test('notes index passes required view variables', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes');

    $response->assertViewHas('notes');
    $response->assertViewHas('allTags');
    $response->assertViewHas('searchTerm');
    $response->assertViewHas('selectedTag');
});

test('notes index returns all notes when no filters applied', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Note::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/notes');

    expect($response->viewData('notes'))->toHaveCount(3);
});

test('notes index returns pinned notes first', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Note::factory()->create(['user_id' => $user->id, 'title' => 'Unpinned Note', 'is_pinned' => false]);
    Note::factory()->create(['user_id' => $user->id, 'title' => 'Pinned Note', 'is_pinned' => true]);

    $response = $this->actingAs($user)->get('/notes');

    $notes = $response->viewData('notes');
    expect($notes->first()->title)->toBe('Pinned Note');
});

test('notes index filters by search term', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Note::factory()->create(['user_id' => $user->id, 'title' => 'Laravel tips and tricks']);
    Note::factory()->create(['user_id' => $user->id, 'title' => 'Completely unrelated topic']);

    $response = $this->actingAs($user)->get('/notes?q=Laravel');

    $response->assertOk();
    $notes = $response->viewData('notes');
    expect($notes)->toHaveCount(1);
    expect($notes->first()->title)->toBe('Laravel tips and tricks');
});

test('notes index returns empty search term variable when no q param', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes');

    expect($response->viewData('searchTerm'))->toBe('');
});

test('notes index passes search term to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes?q=hello');

    expect($response->viewData('searchTerm'))->toBe('hello');
});

test('notes index filters by tag', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $taggedNote = Note::factory()->create(['user_id' => $user->id]);
    NoteTag::factory()->create(['user_id' => $user->id, 'note_id' => $taggedNote->id, 'tag' => 'backend']);

    $untaggedNote = Note::factory()->create(['user_id' => $user->id]);
    NoteTag::factory()->create(['user_id' => $user->id, 'note_id' => $untaggedNote->id, 'tag' => 'frontend']);

    $response = $this->actingAs($user)->get('/notes?tag=backend');

    $response->assertOk();
    $notes = $response->viewData('notes');
    expect($notes)->toHaveCount(1);
    expect($notes->first()->id)->toBe($taggedNote->id);
});

test('notes index passes available tags to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    NoteTag::factory()->create(['user_id' => $user->id, 'tag' => 'alpha']);
    NoteTag::factory()->create(['user_id' => $user->id, 'tag' => 'beta']);

    $response = $this->actingAs($user)->get('/notes');

    $tags = $response->viewData('allTags');
    expect($tags)->toContain('alpha');
    expect($tags)->toContain('beta');
});

test('notes index available tags are distinct', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    NoteTag::factory()->create(['user_id' => $user->id, 'tag' => 'duplicate']);
    NoteTag::factory()->create(['user_id' => $user->id, 'tag' => 'duplicate']);

    $response = $this->actingAs($user)->get('/notes');

    $tags = $response->viewData('allTags');
    expect($tags->filter(fn ($t) => $t === 'duplicate'))->toHaveCount(1);
});

test('notes index passes selected tag to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/notes?tag=myTag');

    expect($response->viewData('selectedTag'))->toBe('myTag');
});
