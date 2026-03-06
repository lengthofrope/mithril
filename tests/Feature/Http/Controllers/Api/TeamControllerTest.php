<?php

declare(strict_types=1);

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns a successful response with all teams', function () {
    /** @var \Tests\TestCase $this */
    Team::factory()->count(2)->create();

    $response = $this->getJson('/api/v1/teams');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(2);
});

test('index returns teams ordered by sort_order', function () {
    /** @var \Tests\TestCase $this */
    Team::factory()->create(['name' => 'Backend', 'sort_order' => 2]);
    Team::factory()->create(['name' => 'Frontend', 'sort_order' => 1]);

    $response = $this->getJson('/api/v1/teams');

    $response->assertOk();

    $names = array_column($response->json('data'), 'name');
    expect($names[0])->toBe('Frontend');
    expect($names[1])->toBe('Backend');
});

test('store creates a new team and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $payload = [
        'name' => 'Engineering',
        'description' => 'Core engineering team',
        'color' => '#ff5733',
    ];

    $response = $this->postJson('/api/v1/teams', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.name'))->toBe('Engineering');

    $this->assertDatabaseHas('teams', ['name' => 'Engineering']);
});

test('store returns 422 when name is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/teams', [
        'description' => 'No name provided',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store returns 422 when name exceeds max length', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/teams', [
        'name' => str_repeat('a', 256),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('update modifies an existing team', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create(['name' => 'Old name']);

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'name' => 'New name',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Updated successfully.',
        ]);

    expect($response->json('data.name'))->toBe('New name');

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'New name',
    ]);
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create();

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'name' => 'Updated',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when team does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->putJson('/api/v1/teams/9999', ['name' => 'Ghost']);

    $response->assertNotFound();
});

test('destroy deletes a team', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create();

    $response = $this->deleteJson("/api/v1/teams/{$team->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('destroy returns 404 when team does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/api/v1/teams/9999');

    $response->assertNotFound();
});
