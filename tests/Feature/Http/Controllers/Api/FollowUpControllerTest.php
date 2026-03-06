<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all follow-ups', function () {
    /** @var \Tests\TestCase $this */
    FollowUp::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/follow-ups');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(3);
});

test('store creates a new follow-up and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $payload = [
        'description' => 'Check in on project status',
        'follow_up_date' => '2026-03-15',
        'status' => FollowUpStatus::Open->value,
    ];

    $response = $this->postJson('/api/v1/follow-ups', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.description'))->toBe('Check in on project status');

    $this->assertDatabaseHas('follow_ups', ['description' => 'Check in on project status']);
});

test('store returns 422 when description is missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/follow-ups', [
        'follow_up_date' => '2026-03-15',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

test('store returns 422 when status is not a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/follow-ups', [
        'description' => 'Test',
        'status' => 'in_review',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('store returns 422 when team_member_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/follow-ups', [
        'description' => 'Test',
        'team_member_id' => 9999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id']);
});

test('store creates follow-up linked to existing team member', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->postJson('/api/v1/follow-ups', [
        'description' => 'Discuss performance',
        'team_member_id' => $member->id,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('follow_ups', [
        'description' => 'Discuss performance',
        'team_member_id' => $member->id,
    ]);
});

test('update modifies an existing follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create(['description' => 'Old description']);

    $response = $this->putJson("/api/v1/follow-ups/{$followUp->id}", [
        'description' => 'Updated description',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.description'))->toBe('Updated description');
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create();

    $response = $this->putJson("/api/v1/follow-ups/{$followUp->id}", [
        'description' => 'Updated',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when follow-up does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->putJson('/api/v1/follow-ups/9999', [
        'description' => 'Ghost',
    ]);

    $response->assertNotFound();
});

test('destroy deletes a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $followUp = FollowUp::factory()->create();

    $response = $this->deleteJson("/api/v1/follow-ups/{$followUp->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);
});

test('destroy returns 404 when follow-up does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/api/v1/follow-ups/9999');

    $response->assertNotFound();
});
