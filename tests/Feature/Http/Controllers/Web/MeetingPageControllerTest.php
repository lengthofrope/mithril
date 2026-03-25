<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\PrepItemType;
use App\Events\MeetingScheduled;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('meetings index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/meetings');

    $response->assertRedirect('/login');
});

test('meetings index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/meetings');

    $response->assertOk();
});

test('meetings index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/meetings');

    $response->assertViewIs('pages.meetings.index');
});

test('meetings index passes upcomingMeetings and pastMeetings to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/meetings');

    $response->assertViewHas('upcomingMeetings');
    $response->assertViewHas('pastMeetings');
});

test('meetings index upcoming contains only future non-done meetings', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();

    $future = Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => true]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->subDay(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/meetings');

    $upcoming = $response->viewData('upcomingMeetings');
    expect($upcoming)->toHaveCount(1);
    expect($upcoming->first()->id)->toBe($future->id);
});

test('meetings index past is empty by default', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->subDay(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => true]);

    $response = $this->actingAs($user)->get('/meetings');

    $past = $response->viewData('pastMeetings');
    expect($past)->toHaveCount(0);
});

test('meetings index past contains done or past-scheduled meetings when show_past is enabled', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();

    $pastDate = Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->subDay(), 'is_done' => false]);
    $doneUpcoming = Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => true]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/meetings?show_past=1');

    $past = $response->viewData('pastMeetings');
    $pastIds = $past->pluck('id')->sort()->values()->all();
    expect($pastIds)->toBe(collect([$pastDate->id, $doneUpcoming->id])->sort()->values()->all());
});

test('meetings index filter by team_id returns only that team meetings', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $otherTeam = Team::factory()->create(['user_id' => $user->id]);

    $match = Meeting::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/meetings?team_id=' . $team->id);

    $upcoming = $response->viewData('upcomingMeetings');
    expect($upcoming)->toHaveCount(1);
    expect($upcoming->first()->id)->toBe($match->id);
});

test('meetings index filter by team_member_id returns meetings with that attendee', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => 'Alice']);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => 'Bob']);

    $aliceMeeting = Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    $aliceMeeting->attendees()->attach($alice->id);

    $bobMeeting = Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    $bobMeeting->attendees()->attach($bob->id);

    $response = $this->actingAs($user)->get('/meetings?team_member_id=' . $alice->id);

    $upcoming = $response->viewData('upcomingMeetings');
    expect($upcoming)->toHaveCount(1);
    expect($upcoming->first()->id)->toBe($aliceMeeting->id);
});

test('meetings index filter by type returns only that meeting type', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();

    $ono = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::Team, 'scheduled_at' => now()->addDay(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/meetings?type=one_on_one');

    $upcoming = $response->viewData('upcomingMeetings');
    expect($upcoming)->toHaveCount(1);
    expect($upcoming->first()->id)->toBe($ono->id);
});

test('meetings index filter by status returns only meetings with that status', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(now()->setTime(12, 0, 0));
    $user = User::factory()->create();

    $scheduled = Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Scheduled, 'scheduled_at' => now()->addDay(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::InProgress, 'scheduled_at' => now()->addDay(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/meetings?status=scheduled');

    $upcoming = $response->viewData('upcomingMeetings');
    expect($upcoming)->toHaveCount(1);
    expect($upcoming->first()->id)->toBe($scheduled->id);
});

test('meetings index AJAX request returns meetings-list partial', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/meetings', ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertViewIs('partials.meetings-list');
});

test('meetings index AJAX response contains upcomingMeetings and pastMeetings', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/meetings', ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertViewHas('upcomingMeetings');
    $response->assertViewHas('pastMeetings');
});

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

test('can create a meeting with required fields', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Sprint Planning',
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('meetings', [
        'user_id' => $user->id,
        'title' => 'Sprint Planning',
        'type' => 'one_on_one',
    ]);
});

test('store creates a meeting with attendees', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $this->actingAs($user)->post('/meetings', [
        'title' => 'Team Sync',
        'type' => 'team',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'attendee_ids' => [$alice->id, $bob->id],
    ]);

    $meeting = Meeting::where('user_id', $user->id)->where('title', 'Team Sync')->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->attendees()->pluck('team_member_id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('store dispatches MeetingScheduled event', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/meetings', [
        'title' => 'Event Test',
        'type' => 'other',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    Event::assertDispatched(MeetingScheduled::class, function (MeetingScheduled $event) use ($user): bool {
        return $event->meeting->user_id === $user->id
            && $event->meeting->title === 'Event Test';
    });
});

test('store redirects to show page after creation', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Redirect Test',
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $meeting = Meeting::where('user_id', $user->id)->where('title', 'Redirect Test')->first();
    $response->assertRedirect(route('meetings.show', $meeting));
});

test('store validation fails without title', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $response->assertSessionHasErrors('title');
});

test('store validation fails without type', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'No Type Meeting',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $response->assertSessionHasErrors('type');
});

test('store validation fails without scheduled_at', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'No Date Meeting',
        'type' => 'one_on_one',
    ]);

    $response->assertSessionHasErrors('scheduled_at');
});

test('store validation fails with invalid type value', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Bad Type',
        'type' => 'invalid_type',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $response->assertSessionHasErrors('type');
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('meetings show returns 200 for the meeting owner', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/meetings/' . $meeting->id);

    $response->assertOk();
});

test('meetings show renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/meetings/' . $meeting->id);

    $response->assertViewIs('pages.meetings.show');
});

test('meetings show passes meeting attendees prepItems breadcrumbs to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/meetings/' . $meeting->id);

    $response->assertViewHas('meeting');
    $response->assertViewHas('breadcrumbs');
    $response->assertViewHas('attendeeOptions');
    $response->assertViewHas('previousMeeting');
    $response->assertViewHas('nextMeeting');
});

test('meetings show previous navigation finds earlier one_on_one meeting with same attendee', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $earlier = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()->subDays(2)]);
    $earlier->attendees()->attach($alice->id);

    $current = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()]);
    $current->attendees()->attach($alice->id);

    $response = $this->actingAs($user)->get('/meetings/' . $current->id);

    expect($response->viewData('previousMeeting')?->id)->toBe($earlier->id);
});

test('meetings show next navigation finds later one_on_one meeting with same attendee', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $current = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()]);
    $current->attendees()->attach($alice->id);

    $later = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()->addDays(2)]);
    $later->attendees()->attach($alice->id);

    $response = $this->actingAs($user)->get('/meetings/' . $current->id);

    expect($response->viewData('nextMeeting')?->id)->toBe($later->id);
});

test('meetings show previous and next are null when no adjacent meetings exist', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne, 'scheduled_at' => now()]);
    $meeting->attendees()->attach($alice->id);

    $response = $this->actingAs($user)->get('/meetings/' . $meeting->id);

    expect($response->viewData('previousMeeting'))->toBeNull();
    expect($response->viewData('nextMeeting'))->toBeNull();
});

test('meetings show returns 404 for a meeting belonging to another user', function () {
    /** @var \Tests\TestCase $this */
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->get('/meetings/' . $meeting->id);

    $response->assertNotFound();
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

test('can update meeting title via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Old Title']);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'title' => 'New Title',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'title' => 'New Title']);
});

test('can update meeting notes via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'notes' => null]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'notes' => 'Meeting notes go here.',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'notes' => 'Meeting notes go here.']);
});

test('can update meeting scheduled_at via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $newDate = now()->addWeek()->toDateTimeString();

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'scheduled_at' => $newDate,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('update response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'title' => 'Updated',
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveKey('saved_at');
    expect($data['saved_at'])->toBeString()->not->toBeEmpty();
});

test('can update meeting type via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'type' => 'team',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'type' => 'team']);
});

test('update rejects invalid meeting type', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'type' => 'invalid_type',
    ]);

    $response->assertSessionHasErrors('type');
});

test('can sync meeting attendees via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member1 = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $member2 = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::Team]);
    $meeting->attendees()->attach($member1->id);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'attendee_ids' => [$member1->id, $member2->id],
    ]);

    $response->assertOk();
    expect($meeting->fresh()->attendees)->toHaveCount(2);
});

test('can clear all meeting attendees via AJAX', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $meeting->attendees()->attach($member->id);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'attendee_ids' => [],
    ]);

    $response->assertOk();
    expect($meeting->fresh()->attendees)->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// Status Transitions
// ---------------------------------------------------------------------------

test('transition from scheduled to in_progress sets started_at', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Scheduled, 'started_at' => null]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/transition', [
        'status' => 'in_progress',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'in_progress']);
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'status' => 'in_progress']);
    expect($meeting->fresh()->started_at)->not->toBeNull();
});

test('transition does not overwrite started_at when already set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $originalStart = now()->subHour();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Scheduled, 'started_at' => $originalStart]);

    $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/transition', [
        'status' => 'in_progress',
    ]);

    expect($meeting->fresh()->started_at->timestamp)->toBe($originalStart->timestamp);
});

test('transition to completed sets ended_at and is_done', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::InProgress]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/transition', [
        'status' => 'completed',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'completed']);
    $fresh = $meeting->fresh();
    expect($fresh->ended_at)->not->toBeNull();
    expect($fresh->is_done)->toBeTrue();
});

test('transition to cancelled sets is_done', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => MeetingStatus::Scheduled, 'is_done' => false]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/transition', [
        'status' => 'cancelled',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'cancelled']);
    expect($meeting->fresh()->is_done)->toBeTrue();
});

test('transition validation fails with invalid status value', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/transition', [
        'status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors('status');
});

// ---------------------------------------------------------------------------
// Mark Done / Undo Done
// ---------------------------------------------------------------------------

test('markDone sets is_done true and status completed', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'is_done' => false, 'status' => MeetingStatus::Scheduled]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/done');

    $response->assertRedirect();
    $fresh = $meeting->fresh();
    expect($fresh->is_done)->toBeTrue();
    expect($fresh->status)->toBe(MeetingStatus::Completed);
});

test('markDone returns JSON success for AJAX request', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'is_done' => false]);

    $response = $this->actingAs($user)->patch(
        '/meetings/' . $meeting->id . '/done',
        [],
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('undoDone sets is_done false and status scheduled', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'is_done' => true, 'status' => MeetingStatus::Completed]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id . '/undone');

    $response->assertRedirect();
    $fresh = $meeting->fresh();
    expect($fresh->is_done)->toBeFalse();
    expect($fresh->status)->toBe(MeetingStatus::Scheduled);
});

test('undoDone returns JSON success for AJAX request', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'is_done' => true]);

    $response = $this->actingAs($user)->patch(
        '/meetings/' . $meeting->id . '/undone',
        [],
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

// ---------------------------------------------------------------------------
// Prep Items
// ---------------------------------------------------------------------------

test('can store a prep item with type and duration_minutes', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'content' => 'Discuss Q1 results',
        'type' => 'agenda_item',
        'duration_minutes' => 15,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meeting_prep_items', [
        'meeting_id' => $meeting->id,
        'content' => 'Discuss Q1 results',
        'type' => 'agenda_item',
        'duration_minutes' => 15,
    ]);
});

test('can store a prep item with team_member_id assignee', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'content' => 'Alice to present update',
        'type' => 'action',
        'team_member_id' => $alice->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meeting_prep_items', [
        'meeting_id' => $meeting->id,
        'team_member_id' => $alice->id,
        'content' => 'Alice to present update',
    ]);
});

test('storePrepItem validation fails without content', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'type' => 'question',
    ]);

    $response->assertSessionHasErrors('content');
});

test('storePrepItem validation fails without meeting_id', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prep-items', [
        'content' => 'Some agenda item',
        'type' => 'agenda_item',
    ]);

    $response->assertSessionHasErrors('meeting_id');
});

test('storePrepItem returns created item data with id and attributes', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'content' => 'Review sprint goals',
        'type' => 'agenda_item',
        'duration_minutes' => 10,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'data' => [
            'id',
            'content',
            'type',
            'duration_minutes',
            'is_discussed',
            'team_member_name',
        ],
    ]);
    $response->assertJsonPath('data.content', 'Review sprint goals');
    $response->assertJsonPath('data.type', 'agenda_item');
    $response->assertJsonPath('data.duration_minutes', 10);
    $response->assertJsonPath('data.is_discussed', false);
    $response->assertJsonPath('data.team_member_name', null);
});

test('storePrepItem returns team member name when assigned', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => 'Bob Smith']);
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'content' => 'Bob presents update',
        'type' => 'action',
        'team_member_id' => $bob->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.team_member_name', 'Bob Smith');
});

test('storePrepItem rejects meeting belonging to another user', function () {
    /** @var \Tests\TestCase $this */
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($attacker)->post('/prep-items', [
        'meeting_id' => $meeting->id,
        'content' => 'Unauthorized item',
        'type' => 'agenda_item',
    ]);

    $response->assertSessionHasErrors('meeting_id');
});

test('can update prep item is_discussed', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $user->id, 'meeting_id' => $meeting->id, 'is_discussed' => false]);

    $response = $this->actingAs($user)->patch('/prep-items/' . $item->id, [
        'is_discussed' => true,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meeting_prep_items', ['id' => $item->id, 'is_discussed' => true]);
});

test('can update prep item content', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $user->id, 'meeting_id' => $meeting->id, 'content' => 'Original content']);

    $response = $this->actingAs($user)->patch('/prep-items/' . $item->id, [
        'content' => 'Updated content',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('meeting_prep_items', ['id' => $item->id, 'content' => 'Updated content']);
});

test('updatePrepItem rejects item belonging to another user', function () {
    /** @var \Tests\TestCase $this */
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $owner->id, 'meeting_id' => $meeting->id]);

    $response = $this->actingAs($attacker)->patch('/prep-items/' . $item->id, [
        'content' => 'Hacked',
    ]);

    $response->assertNotFound();
});

test('can delete a prep item', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $user->id, 'meeting_id' => $meeting->id]);

    $response = $this->actingAs($user)->delete('/prep-items/' . $item->id);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('meeting_prep_items', ['id' => $item->id]);
});

test('destroyPrepItem rejects item belonging to another user', function () {
    /** @var \Tests\TestCase $this */
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $owner->id, 'meeting_id' => $meeting->id]);

    $response = $this->actingAs($attacker)->delete('/prep-items/' . $item->id);

    $response->assertNotFound();
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('can delete a meeting', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete('/meetings/' . $meeting->id);

    $response->assertRedirect(route('meetings.index'));
    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
});

test('destroying a meeting cascades to its prep items', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $item = MeetingPrepItem::factory()->create(['user_id' => $user->id, 'meeting_id' => $meeting->id]);

    $this->actingAs($user)->delete('/meetings/' . $meeting->id);

    $this->assertDatabaseMissing('meeting_prep_items', ['id' => $item->id]);
});

test('destroying a meeting removes its attendee associations', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    $meeting->attendees()->attach($alice->id);

    $this->actingAs($user)->delete('/meetings/' . $meeting->id);

    $this->assertDatabaseEmpty('meeting_attendees');
});

test('destroy returns JSON success for AJAX request', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete(
        '/meetings/' . $meeting->id,
        [],
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
});

test('cannot delete a meeting belonging to another user', function () {
    /** @var \Tests\TestCase $this */
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->delete('/meetings/' . $meeting->id);

    $response->assertNotFound();
    $this->assertDatabaseHas('meetings', ['id' => $meeting->id]);
});

// ---------------------------------------------------------------------------
// Attendee constraints per meeting type
// ---------------------------------------------------------------------------

test('store one_on_one meeting rejects more than one attendee', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => '1-on-1 with too many',
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'attendee_ids' => [$alice->id, $bob->id],
    ]);

    $response->assertSessionHasErrors('attendee_ids');
});

test('store one_on_one meeting allows exactly one attendee', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => '1-on-1 with Alice',
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'attendee_ids' => [$alice->id],
    ]);

    $response->assertRedirect();
    $meeting = Meeting::where('user_id', $user->id)->where('title', '1-on-1 with Alice')->first();
    expect($meeting->attendees)->toHaveCount(1);
});

test('update one_on_one meeting rejects more than one attendee', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne]);
    $meeting->attendees()->attach($alice->id);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'attendee_ids' => [$alice->id, $bob->id],
    ]);

    $response->assertJsonValidationErrors('attendee_ids');
    expect($meeting->fresh()->attendees)->toHaveCount(1);
});

test('store team meeting with team_ids auto-attaches all team members', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Team Sync',
        'type' => 'team',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'team_ids' => [$team->id],
    ]);

    $response->assertRedirect();
    $meeting = Meeting::where('user_id', $user->id)->where('title', 'Team Sync')->first();
    expect($meeting->attendees()->pluck('team_member_id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('store other meeting with team_ids auto-attaches all team members', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['user_id' => $user->id]);
    $teamB = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Cross-team sync',
        'type' => 'other',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'team_ids' => [$teamA->id, $teamB->id],
    ]);

    $response->assertRedirect();
    $meeting = Meeting::where('user_id', $user->id)->where('title', 'Cross-team sync')->first();
    expect($meeting->attendees()->pluck('team_member_id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('store team meeting with team_ids merges with explicit attendee_ids', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $teamA = Team::factory()->create(['user_id' => $user->id]);
    $teamB = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Mixed',
        'type' => 'team',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'team_ids' => [$teamA->id],
        'attendee_ids' => [$bob->id],
    ]);

    $response->assertRedirect();
    $meeting = Meeting::where('user_id', $user->id)->where('title', 'Mixed')->first();
    expect($meeting->attendees()->pluck('team_member_id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('update team meeting with team_ids resolves and syncs attendees', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::Team]);

    $response = $this->actingAs($user)->patch('/meetings/' . $meeting->id, [
        'team_ids' => [$team->id],
    ]);

    $response->assertOk();
    expect($meeting->fresh()->attendees()->pluck('team_member_id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('store one_on_one meeting ignores team_ids', function () {
    /** @var \Tests\TestCase $this */
    Event::fake([MeetingScheduled::class]);
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $alice = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bob = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->post('/meetings', [
        'title' => '1-on-1 ignoring teams',
        'type' => 'one_on_one',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'team_ids' => [$team->id],
        'attendee_ids' => [$alice->id],
    ]);

    $response->assertRedirect();
    $meeting = Meeting::where('user_id', $user->id)->where('title', '1-on-1 ignoring teams')->first();
    expect($meeting->attendees)->toHaveCount(1);
    expect($meeting->attendees->first()->id)->toBe($alice->id);
});

test('show page passes teamOptions for team and other meeting types', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::Team]);

    $response = $this->actingAs($user)->get('/meetings/' . $meeting->id);

    $response->assertViewHas('teamOptions');
});
