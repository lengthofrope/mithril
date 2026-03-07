<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('follow-up index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/follow-ups');

    $response->assertOk();
});

test('follow-up index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/follow-ups');

    $response->assertRedirect('/login');
});

test('follow-up index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/follow-ups');

    $response->assertViewIs('pages.follow-ups.index');
});

test('follow-up index passes sections memberOptions selectedTeamMemberId to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/follow-ups');

    $response->assertViewHas('sections');
    $response->assertViewHas('memberOptions');
    $response->assertViewHas('selectedTeamMemberId');
    expect($response->viewData('sections'))->toHaveKeys(['overdue', 'today', 'thisWeek', 'upcoming']);
});

test('follow-up index groups overdue follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDays(2), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDay(), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString(), 'status' => FollowUpStatus::Open]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['overdue'])->toHaveCount(2);
});

test('follow-up index groups today follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString(), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDay(), 'status' => FollowUpStatus::Open]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['today'])->toHaveCount(1);
});

test('follow-up index groups this week follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::create(2026, 3, 4, 10, 0, 0)); // Wednesday
    $user = User::factory()->create();

    $withinWeek = now()->endOfWeek()->subDay();
    $afterWeek = now()->endOfWeek()->addDays(2);

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => $withinWeek->toDateString(), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => $afterWeek->toDateString(), 'status' => FollowUpStatus::Open]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['thisWeek'])->toHaveCount(1);
});

test('follow-up index groups upcoming follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $upcoming = now()->endOfWeek()->addDays(3);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => $upcoming->toDateString(), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString(), 'status' => FollowUpStatus::Open]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['upcoming'])->toHaveCount(1);
});

test('follow-up index excludes done follow-ups from all buckets', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDay(), 'status' => FollowUpStatus::Done]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString(), 'status' => FollowUpStatus::Done]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['overdue'])->toHaveCount(0);
    expect($response->viewData('sections')['today'])->toHaveCount(0);
});

test('follow-up index filters by team_member_id when provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    FollowUp::factory()->create([
        'user_id' => $user->id,
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
        'team_member_id' => $member->id,
    ]);
    FollowUp::factory()->create([
        'user_id' => $user->id,
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
        'team_member_id' => null,
    ]);

    $response = $this->actingAs($user)->get('/follow-ups?team_member_id=' . $member->id);

    expect($response->viewData('sections')['overdue'])->toHaveCount(1);
    expect($response->viewData('selectedTeamMemberId'))->toBe((string) $member->id);
});

test('mark done redirects back for non-AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->patch("/follow-ups/{$followUp->id}/done");

    $response->assertRedirect('/follow-ups');
    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
});

test('mark done returns JSON for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->patch(
            "/follow-ups/{$followUp->id}/done",
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('snooze redirects back for non-AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'follow_up_date' => now()->toDateString(),
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->patch("/follow-ups/{$followUp->id}/snooze", ['days' => 3]);

    $response->assertRedirect('/follow-ups');
    expect($followUp->fresh()->follow_up_date->toDateString())
        ->toBe(now()->addDays(3)->toDateString());
});

test('convert to task redirects back for non-AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Follow up on meeting notes',
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->post("/follow-ups/{$followUp->id}/convert");

    $response->assertRedirect('/follow-ups');
    $this->assertDatabaseHas('tasks', ['title' => 'Follow up on meeting notes']);
    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
});

test('follow-up index returns only the partial for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/follow-ups', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk();
    $response->assertDontSee('<!DOCTYPE html');
});
