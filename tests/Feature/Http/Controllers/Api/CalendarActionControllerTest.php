<?php

declare(strict_types=1);

use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\Meeting;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

/**
 * Sets up the base fixture: user, team, team member, and calendar event with attendees.
 *
 * @return array{user: User, team: Team, member: TeamMember, event: CalendarEvent}
 */
function makeCalendarFixture(): array
{
    $user   = User::factory()->create(['email' => 'lead@example.com']);
    $team   = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'email'   => 'member@example.com',
    ]);
    $event = CalendarEvent::factory()->create([
        'user_id'   => $user->id,
        'subject'   => 'Sync meeting',
        'attendees' => [
            ['email' => 'lead@example.com',   'name' => 'Lead'],
            ['email' => 'member@example.com', 'name' => 'Member'],
        ],
    ]);

    return compact('user', 'team', 'member', 'event');
}

// ─── Prefill ──────────────────────────────────────────────────────────────────

it('returns prefill data for meeting type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'member' => $member, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$event->id}/prefill/meeting");

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.team_member_id'))->toBe($member->id)
        ->and($response->json('data.scheduled_at'))->toContain($event->start_at->toDateString());
});

it('returns prefill data for task type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'member' => $member, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$event->id}/prefill/task");

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.title'))->toBe($event->subject)
        ->and($response->json('data.team_member_id'))->toBe($member->id)
        ->and($response->json('data.deadline'))->toBe($event->start_at->toDateString());
});

it('returns prefill data for follow-up type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'member' => $member, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$event->id}/prefill/follow-up");

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.description'))->toBe($event->subject)
        ->and($response->json('data.team_member_id'))->toBe($member->id)
        ->and($response->json('data.follow_up_date'))->toBe($event->start_at->toDateString());
});

it('returns prefill data for note type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$event->id}/prefill/note");

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($response->json('data.title'))->toBe($event->subject);
});

it('returns 404 for invalid prefill type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$event->id}/prefill/invalid-type");

    $response->assertNotFound();
});

it('returns 404 for another users calendar event on prefill', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user] = makeCalendarFixture();

    $otherUser  = User::factory()->create();
    $otherEvent = CalendarEvent::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson("/api/v1/calendar-events/{$otherEvent->id}/prefill/meeting");

    $response->assertNotFound();
});

// ─── Create ───────────────────────────────────────────────────────────────────

it('creates a meeting from a calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'member' => $member, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/meeting");

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Created successfully.',
        ]);

    $this->assertDatabaseHas('meetings', [
        'user_id' => $user->id,
    ]);
});

it('creates a task from a calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/task");

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect($response->json('data.resource.title'))->toBe($event->subject);

    $this->assertDatabaseHas('tasks', [
        'title'   => $event->subject,
        'user_id' => $user->id,
    ]);
});

it('creates a follow-up from a calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/follow-up");

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect($response->json('data.resource.title'))->toBe($event->subject);

    $this->assertDatabaseHas('follow_ups', [
        'title'   => $event->subject,
        'user_id' => $user->id,
    ]);
});

it('creates a note from a calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/note");

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect($response->json('data.resource.title'))->toBe($event->subject);

    $this->assertDatabaseHas('notes', [
        'title'   => $event->subject,
        'user_id' => $user->id,
    ]);
});

it('creates a resource with a link to the calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/task");

    $response->assertStatus(201);

    $taskId = $response->json('data.resource.id');

    $this->assertDatabaseHas('calendar_event_links', [
        'calendar_event_id' => $event->id,
        'linkable_type'     => Task::class,
        'linkable_id'       => $taskId,
    ]);
});

it('creates meeting without attendee when no team member matches', function (): void {
    /** @var \Tests\TestCase $this */
    $user  = User::factory()->create(['email' => 'lead@example.com']);
    $event = CalendarEvent::factory()->create([
        'user_id'   => $user->id,
        'subject'   => 'External meeting',
        'attendees' => [
            ['email' => 'lead@example.com', 'name' => 'Lead'],
            ['email' => 'external@other.com', 'name' => 'External'],
        ],
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/meeting");

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('meetings', [
        'title'   => 'External meeting',
        'type'    => 'other',
        'user_id' => $user->id,
    ]);
});

it('returns 400 for invalid create type', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->postJson("/api/v1/calendar-events/{$event->id}/create/invalid-type");

    $response->assertNotFound();
});

// ─── Unlink ───────────────────────────────────────────────────────────────────

it('removes a link without deleting the resource', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $task = Task::factory()->create(['user_id' => $user->id]);
    $link = CalendarEventLink::create([
        'calendar_event_id' => $event->id,
        'linkable_type'     => Task::class,
        'linkable_id'       => $task->id,
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/calendar-events/{$event->id}/links/{$link->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Link removed.',
        ]);

    $this->assertDatabaseMissing('calendar_event_links', ['id' => $link->id]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('returns 404 when link does not belong to the calendar event', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $otherEvent = CalendarEvent::factory()->create(['user_id' => $user->id]);
    $task       = Task::factory()->create(['user_id' => $user->id]);
    $link       = CalendarEventLink::create([
        'calendar_event_id' => $otherEvent->id,
        'linkable_type'     => Task::class,
        'linkable_id'       => $task->id,
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/calendar-events/{$event->id}/links/{$link->id}");

    $response->assertNotFound();
});

it('returns 404 for a non-existent link', function (): void {
    /** @var \Tests\TestCase $this */
    ['user' => $user, 'event' => $event] = makeCalendarFixture();

    $response = $this->actingAs($user)->deleteJson("/api/v1/calendar-events/{$event->id}/links/9999");

    $response->assertNotFound();
});
