<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('undated follow-up appears in prep section on index page', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Prep item no date',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.index'));

    $response->assertOk();
    $response->assertSee('Prep item no date');
    $response->assertSee('Prep');
});

test('overdue scope excludes null-dated follow-ups', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Null date follow-up',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $results = FollowUp::overdue()->get();

    expect($results->pluck('title'))->not->toContain('Null date follow-up');
});

test('dueToday scope excludes null-dated follow-ups', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Null date follow-up today',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $results = FollowUp::dueToday()->get();

    expect($results->pluck('title'))->not->toContain('Null date follow-up today');
});

test('dueThisWeek scope excludes null-dated follow-ups', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Null date follow-up week',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $results = FollowUp::dueThisWeek()->get();

    expect($results->pluck('title'))->not->toContain('Null date follow-up week');
});

test('upcoming scope excludes null-dated follow-ups', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Null date follow-up upcoming',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $results = FollowUp::upcoming()->get();

    expect($results->pluck('title'))->not->toContain('Null date follow-up upcoming');
});

test('follow-up card renders no date set label when follow_up_date is null', function (): void {
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id'        => $user->id,
        'title'          => 'Undated follow-up card check',
        'follow_up_date' => null,
        'status'         => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('follow-ups.index'));

    $response->assertOk();
    $response->assertSee('No date set');
});

test('creating a follow-up without a date stores successfully with null date', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('follow-ups.store'), [
        'title' => 'Follow-up with no date',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $followUp = FollowUp::where('title', 'Follow-up with no date')->first();
    expect($followUp)->not->toBeNull();
    expect($followUp->follow_up_date)->toBeNull();
});
