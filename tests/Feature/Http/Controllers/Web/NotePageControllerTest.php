<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\Team;
use App\Models\TeamMember;
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
    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
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

    $response = $this->actingAs($user)->get('/notes?search=Laravel');

    $response->assertOk();
    $notes = $response->viewData('notes');
    expect($notes)->toHaveCount(1);
    expect($notes->first()->title)->toBe('Laravel tips and tricks');
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

test('notes index filters by team_id', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['user_id' => $user->id]);
    $teamB = Team::factory()->create(['user_id' => $user->id]);

    Note::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    Note::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

    $response = $this->actingAs($user)->get('/notes?team_id=' . $teamA->id);

    $notes = $response->viewData('notes');
    expect($notes)->toHaveCount(1);
    expect($notes->first()->team_id)->toBe($teamA->id);
});

test('notes index filters by team_member_id', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    Note::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    Note::factory()->create(['user_id' => $user->id, 'team_member_id' => null]);

    $response = $this->actingAs($user)->get('/notes?team_member_id=' . $member->id);

    $notes = $response->viewData('notes');
    expect($notes)->toHaveCount(1);
    expect($notes->first()->team_member_id)->toBe($member->id);
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

test('notes index returns only the partial for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/notes', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk();
    $response->assertDontSee('<!DOCTYPE html');
});

test('notes index strips markdown syntax from preview text', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Note::factory()->create([
        'user_id' => $user->id,
        'content' => '# Working Agreements',
    ]);

    $response = $this->actingAs($user)->get('/notes');

    $html = $response->getContent();
    preg_match('/<p class="line-clamp-3[^"]*">\s*(.*?)\s*<\/p>/s', $html, $matches);
    expect($matches)->not->toBeEmpty();
    expect(trim($matches[1]))->not->toContain('# Working');
    expect(trim($matches[1]))->toContain('Working Agreements');
});

test('store creates a new note and redirects to show page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/notes')
        ->post('/notes', [
            'title' => 'My new note',
        ]);

    $note = Note::where('user_id', $user->id)->where('title', 'My new note')->first();
    $response->assertRedirect(route('notes.show', $note));
    $this->assertDatabaseHas('notes', [
        'user_id' => $user->id,
        'title' => 'My new note',
    ]);
});

test('store accepts team_id and team_member_id', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $this->actingAs($user)->post('/notes', [
        'title' => 'Team note',
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);

    $this->assertDatabaseHas('notes', [
        'title' => 'Team note',
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);
});

test('store defaults date to today when not provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post('/notes', [
        'title' => 'Note without date',
    ]);

    $note = Note::where('user_id', $user->id)->where('title', 'Note without date')->first();
    expect($note->date->toDateString())->toBe(now()->toDateString());
});

test('store accepts a custom date', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post('/notes', [
        'title' => 'Backdated note',
        'date' => '2026-01-15',
    ]);

    $note = Note::where('user_id', $user->id)->where('title', 'Backdated note')->first();
    expect($note->date->toDateString())->toBe('2026-01-15');
});

test('update can change the date via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id, 'date' => '2026-03-01']);

    $response = $this->actingAs($user)
        ->patch(
            "/notes/{$note->id}",
            ['date' => '2026-03-10'],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk();
    expect($note->fresh()->date->toDateString())->toBe('2026-03-10');
});

test('store defaults title to Untitled when empty', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post('/notes', [
        'title' => '',
    ]);

    $this->assertDatabaseHas('notes', [
        'user_id' => $user->id,
        'title' => 'Untitled',
    ]);
});

test('show returns 200 for authenticated user with own note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/notes/{$note->id}");

    $response->assertOk();
    $response->assertViewIs('pages.notes.show');
    $response->assertViewHas('note');
    $response->assertViewHas('breadcrumbs');
    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
});

test('show returns 404 for another users note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->get("/notes/{$note->id}");

    $response->assertNotFound();
});

test('destroy deletes a note and redirects', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->from('/notes')
        ->delete("/notes/{$note->id}");

    $response->assertRedirect('/notes');
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
});

test('destroy returns JSON for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->delete(
            "/notes/{$note->id}",
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
});

test('destroy prevents deleting another users note', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)
        ->delete("/notes/{$note->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('notes', ['id' => $note->id]);
});
