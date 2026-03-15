<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\Activity;
use App\Models\CalendarEventLink;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
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
    expect($response->viewData('sections'))->toHaveKeys(['overdue', 'today', 'this_week', 'later']);
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

    expect($response->viewData('sections')['this_week'])->toHaveCount(1);
});

test('follow-up index groups upcoming follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $upcoming = now()->endOfWeek()->addDays(3);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => $upcoming->toDateString(), 'status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString(), 'status' => FollowUpStatus::Open]);

    $response = $this->actingAs($user)->get('/follow-ups');

    expect($response->viewData('sections')['later'])->toHaveCount(1);
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

test('snooze adds days relative to the existing follow_up_date, not today', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'follow_up_date' => now()->addDays(7)->toDateString(),
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->patch("/follow-ups/{$followUp->id}/snooze", ['days' => 3]);

    $response->assertRedirect('/follow-ups');
    expect($followUp->fresh()->follow_up_date->toDateString())
        ->toBe(now()->addDays(10)->toDateString());
});

test('convert to task redirects to new task for non-AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Follow up on meeting notes',
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->post("/follow-ups/{$followUp->id}/convert");

    $task = Task::where('title', 'Follow up on meeting notes')->first();
    $response->assertRedirect(route('tasks.show', $task));
    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
});

test('convert to task returns task URL for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'AJAX convert test',
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->postJson("/follow-ups/{$followUp->id}/convert");

    $task = Task::where('title', 'AJAX convert test')->first();
    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => ['task_url' => route('tasks.show', $task)],
        ]);
    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
});

test('convert to task copies follow_up_date as deadline', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Deadline follow-up',
        'follow_up_date' => '2026-04-15',
        'status' => FollowUpStatus::Open,
    ]);

    $this->actingAs($user)
        ->post("/follow-ups/{$followUp->id}/convert");

    $this->assertDatabaseHas('tasks', [
        'title' => 'Deadline follow-up',
        'deadline' => '2026-04-15 00:00:00',
    ]);
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

test('follow-up index filters by search term', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Review quarterly report',
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
    ]);
    FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Schedule team meeting',
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get('/follow-ups?search=quarterly');

    expect($response->viewData('sections')['overdue'])->toHaveCount(1);
    expect($response->viewData('sections')['overdue']->first()->description)->toBe('Review quarterly report');
});

test('store creates a new follow-up and redirects to show page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->post('/follow-ups', [
            'description' => 'Check on project status',
            'follow_up_date' => '2026-03-15',
        ]);

    $followUp = FollowUp::where('user_id', $user->id)->where('description', 'Check on project status')->first();
    $response->assertRedirect(route('follow-ups.show', $followUp));
    $this->assertDatabaseHas('follow_ups', [
        'user_id' => $user->id,
        'description' => 'Check on project status',
        'follow_up_date' => '2026-03-15 00:00:00',
        'status' => FollowUpStatus::Open->value,
    ]);
});

test('store requires description', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->post('/follow-ups', [
            'follow_up_date' => '2026-03-15',
        ]);

    $response->assertSessionHasErrors('description');
});

test('store defaults follow_up_date to today when not provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->post('/follow-ups', [
        'description' => 'Follow up immediately',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'user_id' => $user->id,
        'description' => 'Follow up immediately',
        'follow_up_date' => now()->startOfDay()->toDateTimeString(),
    ]);
});

test('store accepts optional team_member_id and waiting_on', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post('/follow-ups', [
        'description' => 'Check with team',
        'team_member_id' => $member->id,
        'waiting_on' => 'John',
        'follow_up_date' => '2026-03-20',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'description' => 'Check with team',
        'team_member_id' => $member->id,
        'waiting_on' => 'John',
    ]);
});

test('destroy deletes a follow-up and redirects', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->from('/follow-ups')
        ->delete("/follow-ups/{$followUp->id}");

    $response->assertRedirect('/follow-ups');
    $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);
});

test('destroy returns JSON for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->delete(
            "/follow-ups/{$followUp->id}",
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);
});

test('destroy prevents deleting another users follow-up', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $otherUser->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)
        ->delete("/follow-ups/{$followUp->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('follow_ups', ['id' => $followUp->id]);
});

test('follow-up index filters by team_id via team members', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['user_id' => $user->id]);
    $teamB = Team::factory()->create(['user_id' => $user->id]);
    $memberA = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $memberB = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

    FollowUp::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $memberA->id,
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
    ]);
    FollowUp::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $memberB->id,
        'follow_up_date' => now()->subDay(),
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get('/follow-ups?team_id=' . $teamA->id);

    expect($response->viewData('sections')['overdue'])->toHaveCount(1);
    expect($response->viewData('sections')['overdue']->first()->team_member_id)->toBe($memberA->id);
});

test('show returns 200 for authenticated user with own follow-up', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get("/follow-ups/{$followUp->id}");

    $response->assertOk();
    $response->assertViewIs('pages.follow-ups.show');
    $response->assertViewHas('followUp');
    $response->assertViewHas('breadcrumbs');
    $response->assertViewHas('statusOptions');
    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
});

test('show returns 404 for another users follow-up', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $otherUser->id,
        'status' => FollowUpStatus::Open,
    ]);

    $response = $this->actingAs($user)->get("/follow-ups/{$followUp->id}");

    $response->assertNotFound();
});

test('convert to task transfers activities to the new task', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Transfer activities test',
        'status' => FollowUpStatus::Open,
    ]);

    Activity::factory()->count(2)->create([
        'user_id' => $user->id,
        'activityable_type' => FollowUp::class,
        'activityable_id' => $followUp->id,
    ]);

    $this->actingAs($user)
        ->post("/follow-ups/{$followUp->id}/convert");

    $task = Task::where('title', 'Transfer activities test')->first();
    expect($task->activities)->toHaveCount(2);
    expect($followUp->fresh()->activities()->whereNot('type', \App\Enums\ActivityType::System)->count())->toBe(0);
});

test('convert to task transfers calendar event links to the new task', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Transfer links test',
        'status' => FollowUpStatus::Open,
    ]);

    CalendarEventLink::factory()->create([
        'linkable_type' => FollowUp::class,
        'linkable_id' => $followUp->id,
    ]);

    $this->actingAs($user)
        ->post("/follow-ups/{$followUp->id}/convert");

    $task = Task::where('title', 'Transfer links test')->first();
    expect(CalendarEventLink::where('linkable_type', Task::class)->where('linkable_id', $task->id)->count())->toBe(1);
    expect(CalendarEventLink::where('linkable_type', FollowUp::class)->where('linkable_id', $followUp->id)->count())->toBe(0);
});

test('convert to task transfers email links to the new task', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $followUp = FollowUp::factory()->create([
        'user_id' => $user->id,
        'description' => 'Transfer email links test',
        'status' => FollowUpStatus::Open,
    ]);

    EmailLink::factory()->create([
        'linkable_type' => FollowUp::class,
        'linkable_id' => $followUp->id,
    ]);

    $this->actingAs($user)
        ->post("/follow-ups/{$followUp->id}/convert");

    $task = Task::where('title', 'Transfer email links test')->first();
    expect(EmailLink::where('linkable_type', Task::class)->where('linkable_id', $task->id)->count())->toBe(1);
    expect(EmailLink::where('linkable_type', FollowUp::class)->where('linkable_id', $followUp->id)->count())->toBe(0);
});
