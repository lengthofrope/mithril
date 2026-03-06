<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all team members', function () {
    /** @var \Tests\TestCase $this */
    TeamMember::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/team-members');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(3);
});

test('store creates a new team member and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create();

    $payload = [
        'team_id' => $team->id,
        'name' => 'Jane Doe',
        'role' => 'Developer',
        'email' => 'jane@example.com',
        'status' => MemberStatus::Available->value,
        'bila_interval_days' => 14,
    ];

    $response = $this->postJson('/api/v1/team-members', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.name'))->toBe('Jane Doe');

    $this->assertDatabaseHas('team_members', [
        'name' => 'Jane Doe',
        'team_id' => $team->id,
    ]);
});

test('store returns 422 when required fields are missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/team-members', [
        'role' => 'Developer',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id', 'name']);
});

test('store returns 422 when team_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/team-members', [
        'team_id' => 9999,
        'name' => 'John',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id']);
});

test('store returns 422 when status is invalid', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create();

    $response = $this->postJson('/api/v1/team-members', [
        'team_id' => $team->id,
        'name' => 'Jane',
        'status' => 'on_vacation',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('update modifies an existing team member', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create(['name' => 'Old name']);

    $response = $this->putJson("/api/v1/team-members/{$member->id}", [
        'team_id' => $member->team_id,
        'name' => 'New name',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.name'))->toBe('New name');
});

test('destroy deletes a team member', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->deleteJson("/api/v1/team-members/{$member->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
});

test('destroy returns 404 when team member does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/api/v1/team-members/9999');

    $response->assertNotFound();
});
