<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    FollowUp::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/follow-ups');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(3);
});

test('store creates a new follow-up and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $payload = [
        'description' => 'Check in on project status',
        'follow_up_date' => '2026-03-15',
        'status' => FollowUpStatus::Open->value,
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/follow-ups', $payload);

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
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/follow-ups', [
        'follow_up_date' => '2026-03-15',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

test('store returns 422 when status is not a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/follow-ups', [
        'description' => 'Test',
        'status' => 'in_review',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('store returns 422 when team_member_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/follow-ups', [
        'description' => 'Test',
        'team_member_id' => 9999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id']);
});

test('store creates follow-up linked to existing team member', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/follow-ups', [
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
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create(['user_id' => $user->id, 'description' => 'Old description']);

    $response = $this->actingAs($user)->putJson("/api/v1/follow-ups/{$followUp->id}", [
        'description' => 'Updated description',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.description'))->toBe('Updated description');
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/follow-ups/{$followUp->id}", [
        'description' => 'Updated',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when follow-up does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/follow-ups/9999', [
        'description' => 'Ghost',
    ]);

    $response->assertNotFound();
});

test('destroy deletes a follow-up', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/follow-ups/{$followUp->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);
});

test('destroy returns 404 when follow-up does not exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson('/api/v1/follow-ups/9999');

    $response->assertNotFound();
});
