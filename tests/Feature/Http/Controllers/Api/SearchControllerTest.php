<?php

declare(strict_types=1);

use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('search returns 422 when query is empty', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/search?q=');

    $response->assertStatus(422);
});

test('search returns 422 when query is one character', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/search?q=a');

    $response->assertStatus(422);
});

test('search returns 422 when no q parameter is provided', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/search');

    $response->assertStatus(422);
});

test('search returns success with grouped results structure when query is valid', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/search?q=ab');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => [
                'tasks',
                'notes',
                'follow_ups',
                'team_members',
            ],
        ]);
});

test('search returns empty results when nothing matches', function () {
    /** @var \Tests\TestCase $this */
    Task::factory()->create(['user_id' => $this->user->id,'title' => 'Completely different']);
    Note::factory()->create(['user_id' => $this->user->id,'title' => 'Nothing relevant here']);

    $response = $this->getJson('/api/v1/search?q=xyznonexistent');

    $response->assertOk();
    expect($response->json('data.tasks'))->toBeEmpty();
    expect($response->json('data.notes'))->toBeEmpty();
    expect($response->json('data.follow_ups'))->toBeEmpty();
    expect($response->json('data.team_members'))->toBeEmpty();
});

test('search returns matching tasks by title', function () {
    /** @var \Tests\TestCase $this */
    Task::factory()->create(['user_id' => $this->user->id,'title' => 'Fix the authentication bug']);
    Task::factory()->create(['user_id' => $this->user->id,'title' => 'Write unit tests']);

    $response = $this->getJson('/api/v1/search?q=authentication');

    $response->assertOk();
    expect($response->json('data.tasks'))->toHaveCount(1);
    expect($response->json('data.tasks.0.title'))->toBe('Fix the authentication bug');
});

test('search returns matching notes by title', function () {
    /** @var \Tests\TestCase $this */
    Note::factory()->create(['user_id' => $this->user->id,'title' => 'Deployment checklist']);
    Note::factory()->create(['user_id' => $this->user->id,'title' => 'Team meeting notes']);

    $response = $this->getJson('/api/v1/search?q=Deployment');

    $response->assertOk();
    expect($response->json('data.notes'))->toHaveCount(1);
    expect($response->json('data.notes.0.title'))->toBe('Deployment checklist');
});

test('search returns matching follow-ups by description', function () {
    /** @var \Tests\TestCase $this */
    FollowUp::factory()->create(['user_id' => $this->user->id, 'description' => 'Follow up on contract renewal']);
    FollowUp::factory()->create(['user_id' => $this->user->id, 'description' => 'Check in with stakeholders']);

    $response = $this->getJson('/api/v1/search?q=contract');

    $response->assertOk();
    expect($response->json('data.follow_ups'))->toHaveCount(1);
});

test('search returns matching team members by name', function () {
    /** @var \Tests\TestCase $this */
    TeamMember::factory()->create(['user_id' => $this->user->id, 'name' => 'Alice Johnson']);
    TeamMember::factory()->create(['user_id' => $this->user->id, 'name' => 'Bob Smith']);

    $response = $this->getJson('/api/v1/search?q=Alice');

    $response->assertOk();
    expect($response->json('data.team_members'))->toHaveCount(1);
    expect($response->json('data.team_members.0.name'))->toBe('Alice Johnson');
});

test('search error response includes descriptive message', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/search?q=x');

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('2');
});
