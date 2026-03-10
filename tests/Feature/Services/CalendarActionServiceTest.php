<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\CalendarActionService;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email'           => 'me@example.com',
        'microsoft_email' => 'me@company.com',
    ]);
    $this->actingAs($this->user);
    $this->service = new CalendarActionService();
});

// --- resolveTeamMember ---

it('resolves a single matching team member by microsoft_email', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'colleague@company.com',
        'email'           => null,
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'colleague@company.com', 'name' => 'Colleague'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event)?->id)->toBe($member->id);
});

it('resolves a single matching team member by email', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => null,
        'email'           => 'colleague@example.com',
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'colleague@example.com', 'name' => 'Colleague'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event)?->id)->toBe($member->id);
});

it('returns null when no team members match attendees', function (): void {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'other@company.com',
        'email'           => 'other@example.com',
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'nobody@nowhere.com', 'name' => 'Nobody'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event))->toBeNull();
});

it('returns null when multiple team members match attendees', function (): void {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'alice@company.com',
        'email'           => null,
    ]);
    TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'bob@company.com',
        'email'           => null,
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'alice@company.com', 'name' => 'Alice'],
            ['email' => 'bob@company.com', 'name' => 'Bob'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event))->toBeNull();
});

it('excludes the logged-in user email from matching', function (): void {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'me@company.com',
        'email'           => 'me@example.com',
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'me@example.com', 'name' => 'Me'],
            ['email' => 'me@company.com', 'name' => 'Me (Work)'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event))->toBeNull();
});

it('matches emails case-insensitively', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'microsoft_email' => 'Colleague@Company.COM',
        'email'           => null,
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => [
            ['email' => 'colleague@company.com', 'name' => 'Colleague'],
        ],
    ]);

    expect($this->service->resolveTeamMember($event)?->id)->toBe($member->id);
});

it('returns null when attendees is null', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'attendees' => null,
    ]);

    expect($this->service->resolveTeamMember($event))->toBeNull();
});

// --- buildPrefillData ---

it('builds prefill data for bila', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id'         => $this->user->id,
        'team_id'         => $team->id,
        'name'            => 'Alice',
        'microsoft_email' => 'alice@company.com',
    ]);

    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'start_at'  => '2026-03-15 10:00:00',
        'attendees' => [
            ['email' => 'alice@company.com', 'name' => 'Alice'],
        ],
    ]);

    $data = $this->service->buildPrefillData($event, 'bila');

    expect($data)->toMatchArray([
        'team_member_id'   => $member->id,
        'team_member_name' => 'Alice',
        'scheduled_date'   => '2026-03-15',
    ]);
});

it('builds prefill data for task', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'subject'   => 'Review proposal',
        'start_at'  => '2026-03-15 10:00:00',
        'attendees' => null,
    ]);

    $data = $this->service->buildPrefillData($event, 'task');

    expect($data)->toMatchArray([
        'team_member_id'   => null,
        'team_member_name' => null,
        'title'            => 'Review proposal',
        'deadline'         => '2026-03-15',
    ]);
});

it('builds prefill data for follow-up', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'subject'   => 'Check on deliverable',
        'start_at'  => '2026-03-20 14:00:00',
        'attendees' => null,
    ]);

    $data = $this->service->buildPrefillData($event, 'follow-up');

    expect($data)->toMatchArray([
        'team_member_id'   => null,
        'team_member_name' => null,
        'description'      => 'Check on deliverable',
        'follow_up_date'   => '2026-03-20',
    ]);
});

it('builds prefill data for note', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'subject'   => 'Sprint retrospective notes',
        'start_at'  => '2026-03-18 09:00:00',
        'attendees' => null,
    ]);

    $data = $this->service->buildPrefillData($event, 'note');

    expect($data)->toMatchArray([
        'team_member_id'   => null,
        'team_member_name' => null,
        'title'            => 'Sprint retrospective notes',
    ]);
});

it('throws exception for invalid resource type', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->buildPrefillData($event, 'invalid-type'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid resource type: invalid-type');
});

it('includes null team_member when no match found', function (): void {
    $event = CalendarEvent::factory()->create([
        'user_id'   => $this->user->id,
        'subject'   => 'Solo meeting',
        'start_at'  => '2026-03-10 09:00:00',
        'attendees' => [],
    ]);

    $data = $this->service->buildPrefillData($event, 'task');

    expect($data['team_member_id'])->toBeNull();
    expect($data['team_member_name'])->toBeNull();
});

// --- linkResource ---

it('creates a link between event and resource', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
    ]);
    $event = CalendarEvent::factory()->create(['user_id' => $this->user->id]);
    $bila  = Bila::factory()->create([
        'user_id'        => $this->user->id,
        'team_member_id' => $member->id,
    ]);

    $link = $this->service->linkResource($event, $bila);

    expect($link)->toBeInstanceOf(CalendarEventLink::class);
    expect($link->calendar_event_id)->toBe($event->id);
    expect($link->linkable_type)->toBe(Bila::class);
    expect($link->linkable_id)->toBe($bila->id);

    $this->assertDatabaseHas('calendar_event_links', [
        'calendar_event_id' => $event->id,
        'linkable_type'     => Bila::class,
        'linkable_id'       => $bila->id,
    ]);
});

it('prevents duplicate links', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
    ]);
    $event = CalendarEvent::factory()->create(['user_id' => $this->user->id]);
    $bila  = Bila::factory()->create([
        'user_id'        => $this->user->id,
        'team_member_id' => $member->id,
    ]);

    $link1 = $this->service->linkResource($event, $bila);
    $link2 = $this->service->linkResource($event, $bila);

    expect($link1->id)->toBe($link2->id);
    expect(CalendarEventLink::count())->toBe(1);
});

// --- getLinkedResources ---

it('returns linked resources with eager-loaded models', function (): void {
    $team   = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
    ]);
    $event = CalendarEvent::factory()->create(['user_id' => $this->user->id]);
    $bila  = Bila::factory()->create([
        'user_id'        => $this->user->id,
        'team_member_id' => $member->id,
    ]);

    CalendarEventLink::create([
        'calendar_event_id' => $event->id,
        'linkable_type'     => Bila::class,
        'linkable_id'       => $bila->id,
    ]);

    $links = $this->service->getLinkedResources($event);

    expect($links)->toHaveCount(1);
    expect($links->first())->toBeInstanceOf(CalendarEventLink::class);
    expect($links->first()->linkable)->toBeInstanceOf(Bila::class);
    expect($links->first()->linkable->id)->toBe($bila->id);
});

it('returns empty collection when no links exist', function (): void {
    $event = CalendarEvent::factory()->create(['user_id' => $this->user->id]);

    $links = $this->service->getLinkedResources($event);

    expect($links)->toBeEmpty();
});
