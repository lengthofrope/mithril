<?php

declare(strict_types=1);

use App\Models\Agreement;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all agreements', function () {
    /** @var \Tests\TestCase $this */
    Agreement::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/agreements');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(3);
});

test('store creates a new agreement and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $payload = [
        'team_member_id' => $member->id,
        'description' => 'Deliver feature X by end of sprint',
        'agreed_date' => '2026-03-01',
    ];

    $response = $this->postJson('/api/v1/agreements', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.description'))->toBe('Deliver feature X by end of sprint');

    $this->assertDatabaseHas('agreements', [
        'description' => 'Deliver feature X by end of sprint',
        'team_member_id' => $member->id,
    ]);
});

test('store returns 422 when required fields are missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/agreements', [
        'follow_up_date' => '2026-04-01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id', 'description', 'agreed_date']);
});

test('store returns 422 when team_member_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/agreements', [
        'team_member_id' => 9999,
        'description' => 'Some agreement',
        'agreed_date' => '2026-03-01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id']);
});

test('store returns 422 when agreed_date is not a valid date', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->postJson('/api/v1/agreements', [
        'team_member_id' => $member->id,
        'description' => 'Some agreement',
        'agreed_date' => 'not-a-date',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['agreed_date']);
});

test('store creates an agreement with optional follow_up_date', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->postJson('/api/v1/agreements', [
        'team_member_id' => $member->id,
        'description' => 'Agreement with follow-up',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => '2026-04-01',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('agreements', [
        'follow_up_date' => '2026-04-01 00:00:00',
    ]);
});

test('update modifies an existing agreement', function () {
    /** @var \Tests\TestCase $this */
    $agreement = Agreement::factory()->create(['description' => 'Old description']);

    $response = $this->putJson("/api/v1/agreements/{$agreement->id}", [
        'team_member_id' => $agreement->team_member_id,
        'description' => 'Updated description',
        'agreed_date' => $agreement->agreed_date->toDateString(),
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.description'))->toBe('Updated description');
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $agreement = Agreement::factory()->create();

    $response = $this->putJson("/api/v1/agreements/{$agreement->id}", [
        'team_member_id' => $agreement->team_member_id,
        'description' => 'Updated',
        'agreed_date' => $agreement->agreed_date->toDateString(),
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when agreement does not exist', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->putJson('/api/v1/agreements/9999', [
        'team_member_id' => $member->id,
        'description' => 'Ghost',
        'agreed_date' => '2026-03-01',
    ]);

    $response->assertNotFound();
});

test('destroy deletes an agreement', function () {
    /** @var \Tests\TestCase $this */
    $agreement = Agreement::factory()->create();

    $response = $this->deleteJson("/api/v1/agreements/{$agreement->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('agreements', ['id' => $agreement->id]);
});

test('destroy returns 404 when agreement does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/api/v1/agreements/9999');

    $response->assertNotFound();
});
