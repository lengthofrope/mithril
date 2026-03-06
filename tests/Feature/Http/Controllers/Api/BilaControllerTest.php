<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index returns all bilas', function () {
    /** @var \Tests\TestCase $this */
    Bila::factory()->count(2)->create();

    $response = $this->getJson('/api/v1/bilas');

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data'))->toHaveCount(2);
});

test('store creates a new bila and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $payload = [
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-03-20',
        'notes' => 'Prep notes for the meeting.',
    ];

    $response = $this->postJson('/api/v1/bilas', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    expect($response->json('data.team_member_id'))->toBe($member->id);

    $this->assertDatabaseHas('bilas', [
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-03-20 00:00:00',
    ]);
});

test('store returns 422 when required fields are missing', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/bilas', [
        'notes' => 'Notes without required fields',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id', 'scheduled_date']);
});

test('store returns 422 when team_member_id does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/api/v1/bilas', [
        'team_member_id' => 9999,
        'scheduled_date' => '2026-03-20',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['team_member_id']);
});

test('store returns 422 when scheduled_date is not a valid date', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->postJson('/api/v1/bilas', [
        'team_member_id' => $member->id,
        'scheduled_date' => 'not-a-date',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_date']);
});

test('update modifies an existing bila', function () {
    /** @var \Tests\TestCase $this */
    $bila = Bila::factory()->create();

    $response = $this->putJson("/api/v1/bilas/{$bila->id}", [
        'team_member_id' => $bila->team_member_id,
        'scheduled_date' => '2026-04-01',
        'notes' => 'Updated notes',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('bilas', [
        'id' => $bila->id,
        'notes' => 'Updated notes',
    ]);
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $bila = Bila::factory()->create();

    $response = $this->putJson("/api/v1/bilas/{$bila->id}", [
        'team_member_id' => $bila->team_member_id,
        'scheduled_date' => '2026-04-01',
    ]);

    $response->assertOk();

    expect($response->json('saved_at'))->not->toBeNull();
});

test('update returns 404 when bila does not exist', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->putJson('/api/v1/bilas/9999', [
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-04-01',
    ]);

    $response->assertNotFound();
});

test('destroy deletes a bila', function () {
    /** @var \Tests\TestCase $this */
    $bila = Bila::factory()->create();

    $response = $this->deleteJson("/api/v1/bilas/{$bila->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Deleted successfully.',
        ]);

    $this->assertDatabaseMissing('bilas', ['id' => $bila->id]);
});

test('destroy returns 404 when bila does not exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/api/v1/bilas/9999');

    $response->assertNotFound();
});
