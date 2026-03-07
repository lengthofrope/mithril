<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Store team ---

test('store team creates a new team and redirects back', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/teams', [
        'name' => 'Engineering',
        'description' => 'The engineering team',
        'color' => '#3b82f6',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('teams', [
        'user_id' => $user->id,
        'name' => 'Engineering',
        'description' => 'The engineering team',
        'color' => '#3b82f6',
    ]);
});

test('store team requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/teams', [
        'description' => 'No name given',
    ]);

    $response->assertSessionHasErrors('name');
});

test('store team works without optional fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/teams', [
        'name' => 'Minimal Team',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('teams', ['name' => 'Minimal Team']);
});

// --- Update team ---

test('update team changes fields and redirects back', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $response = $this->actingAs($user)->patch("/teams/{$team->id}", [
        'name' => 'New Name',
        'color' => '#ef4444',
    ]);

    $response->assertRedirect();
    $team->refresh();
    expect($team->name)->toBe('New Name');
    expect($team->color)->toBe('#ef4444');
});

test('update team requires a name', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch("/teams/{$team->id}", [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
});

// --- Delete team ---

test('delete team removes the team and redirects to index', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete("/teams/{$team->id}");

    $response->assertRedirect(route('teams.index'));
    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('delete team also removes its members', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    TeamMember::factory()->count(2)->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $this->actingAs($user)->delete("/teams/{$team->id}");

    $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
});

// --- Store team member ---

test('store team member creates a member and redirects back', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/teams/{$team->id}/members", [
        'name' => 'Jane Doe',
        'role' => 'Developer',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('team_members', [
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Jane Doe',
        'role' => 'Developer',
        'email' => 'jane@example.com',
    ]);
});

test('store team member requires a name', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/teams/{$team->id}/members", [
        'role' => 'Designer',
    ]);

    $response->assertSessionHasErrors('name');
});

test('store team member works with only a name', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/teams/{$team->id}/members", [
        'name' => 'John',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('team_members', ['name' => 'John', 'team_id' => $team->id]);
});

// --- Delete team member ---

test('delete team member removes the member and redirects to team page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/teams/member/{$member->id}");

    $response->assertRedirect(route('teams.show', $team->id));
    $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
});

// --- Auth guards ---

test('store team requires authentication', function () {
    $response = $this->post('/teams', ['name' => 'Test']);

    $response->assertRedirect('/login');
});

test('store team member requires authentication', function () {
    $team = Team::factory()->create();

    $response = $this->post("/teams/{$team->id}/members", ['name' => 'Test']);

    $response->assertRedirect('/login');
});
