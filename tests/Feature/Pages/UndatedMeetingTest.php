<?php

declare(strict_types=1);

use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating a meeting without scheduled_at succeeds via store action', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('meetings.store'), [
        'title' => 'Prep meeting',
        'type'  => MeetingType::Other->value,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(Meeting::where('title', 'Prep meeting')->exists())->toBeTrue();

    $meeting = Meeting::where('title', 'Prep meeting')->first();
    expect($meeting->scheduled_at)->toBeNull();
});

test('meeting index renders undated meetings section when undated meetings exist', function (): void {
    $user = User::factory()->create();

    Meeting::factory()->create([
        'user_id'      => $user->id,
        'title'        => 'Undated Prep',
        'type'         => MeetingType::Other->value,
        'scheduled_at' => null,
        'is_done'      => false,
    ]);

    $response = $this->actingAs($user)->get(route('meetings.index'));

    $response->assertOk();
    $response->assertSee('Undated Prep');
    $response->assertSee('Undated / Prep');
});

test('meeting index excludes undated meetings from upcoming section', function (): void {
    $user = User::factory()->create();

    Meeting::factory()->create([
        'user_id'      => $user->id,
        'title'        => 'Dated Upcoming',
        'type'         => MeetingType::Other->value,
        'scheduled_at' => now()->addDays(3)->toDateString(),
        'is_done'      => false,
    ]);

    Meeting::factory()->create([
        'user_id'      => $user->id,
        'title'        => 'Undated Only',
        'type'         => MeetingType::Other->value,
        'scheduled_at' => null,
        'is_done'      => false,
    ]);

    $response = $this->actingAs($user)->get(route('meetings.index'));

    $response->assertOk();
    $response->assertSee('Dated Upcoming');
    $response->assertSee('Undated Only');
});

test('meeting index ajax response includes undated meetings variable', function (): void {
    $user = User::factory()->create();

    Meeting::factory()->create([
        'user_id'      => $user->id,
        'title'        => 'Ajax Undated',
        'type'         => MeetingType::Other->value,
        'scheduled_at' => null,
        'is_done'      => false,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('meetings.index'));

    $response->assertOk();
    $response->assertSee('Ajax Undated');
});

test('meeting card renders no date set when scheduled_at is null', function (): void {
    $user = User::factory()->create();

    Meeting::factory()->create([
        'user_id'      => $user->id,
        'title'        => 'No Date Meeting',
        'type'         => MeetingType::Other->value,
        'scheduled_at' => null,
        'is_done'      => false,
    ]);

    $response = $this->actingAs($user)->get(route('meetings.index'));

    $response->assertOk();
    $response->assertSee('No date set');
});
