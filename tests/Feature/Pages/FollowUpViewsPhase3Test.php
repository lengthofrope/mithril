<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('private follow-up card renders privacy shield reveal control', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Secret follow-up content',
        'is_private'     => true,
        'follow_up_date' => now()->toDateString(),
        'status'         => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.index'));

    $response->assertOk();
    $response->assertSee('Private — click to reveal');
});

test('non-private follow-up card renders title plainly', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Public follow-up title',
        'is_private'     => false,
        'follow_up_date' => now()->toDateString(),
        'status'         => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.index'));

    $response->assertOk();
    $response->assertSee('Public follow-up title');
    $response->assertDontSee('Private — click to reveal');
});

test('follow-ups index renders Priority Status and Private filter labels', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('follow-ups.index'));

    $response->assertOk();
    $response->assertSee('filter-priority', false);
    $response->assertSee('filter-status', false);
    $response->assertSee('filter-is_private', false);
});

test('dashboard follow-ups partial renders priority badge for a follow-up', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Urgent dashboard item',
        'priority'       => Priority::Urgent,
        'follow_up_date' => now()->toDateString(),
        'status'         => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    // The inline-select-pill in follow-up-card renders the priority label
    $response->assertSee('Urgent');
});

test('creating a follow-up via the store route with title and priority and is_private persists all fields', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('follow-ups.store'), [
        'title'      => 'Modal created follow-up',
        'priority'   => 'high',
        'is_private' => '1',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'user_id'    => $user->id,
        'title'      => 'Modal created follow-up',
        'priority'   => 'high',
        'is_private' => true,
    ]);
});

test('creating a follow-up with description stores description body', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('follow-ups.store'), [
        'title'       => 'Follow-up with body',
        'description' => 'Detailed body text for this follow-up',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'title'       => 'Follow-up with body',
        'description' => 'Detailed body text for this follow-up',
    ]);
});

test('follow-up show page renders editable title field', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'title'   => 'Follow-up title for show',
        'status'  => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.show', $followUp));

    $response->assertOk();
    $response->assertSee('Follow-up title for show');
    // The auto-save-field renders an input with name="title"
    $response->assertSee('name="title"', false);
});

test('follow-up show page renders description textarea', function (): void {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $followUp = FollowUp::factory()->create([
        'user_id'     => $user->id,
        'title'       => 'Follow-up for description check',
        'description' => 'The long body text',
        'status'      => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.show', $followUp));

    $response->assertOk();
    $response->assertSee('The long body text');
});
