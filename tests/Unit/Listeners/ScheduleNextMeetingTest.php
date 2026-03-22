<?php

declare(strict_types=1);

use App\Enums\MeetingType;
use App\Events\MeetingScheduled;
use App\Listeners\ScheduleNextMeeting;
use App\Models\Meeting;
use App\Models\TeamMember;

test('updates next_meeting_date on attendee based on interval', function () {
    $member = TeamMember::factory()->create(['meeting_interval_days' => 14]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::OneOnOne,
        'scheduled_at' => '2026-03-06 10:00:00',
    ]);
    $meeting->attendees()->attach($member->id);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member->refresh();
    expect($member->next_meeting_date->toDateString())->toBe('2026-03-20');
});

test('calculates next meeting date by adding interval_days to scheduled_at', function () {
    $member = TeamMember::factory()->create(['meeting_interval_days' => 7]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::OneOnOne,
        'scheduled_at' => '2026-04-01 09:00:00',
    ]);
    $meeting->attendees()->attach($member->id);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member->refresh();
    expect($member->next_meeting_date->toDateString())->toBe('2026-04-08');
});

test('does not update next_meeting_date when meeting_interval_days is zero', function () {
    $member = TeamMember::factory()->create([
        'meeting_interval_days' => 0,
        'next_meeting_date' => null,
    ]);
    $meeting = Meeting::factory()->create(['type' => MeetingType::OneOnOne]);
    $meeting->attendees()->attach($member->id);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member->refresh();
    expect($member->next_meeting_date)->toBeNull();
});

test('does not update for team meetings', function () {
    $member = TeamMember::factory()->create([
        'meeting_interval_days' => 14,
        'next_meeting_date' => null,
    ]);
    $meeting = Meeting::factory()->create(['type' => MeetingType::Team]);
    $meeting->attendees()->attach($member->id);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member->refresh();
    expect($member->next_meeting_date)->toBeNull();
});

test('does not update when multiple attendees are present', function () {
    $member1 = TeamMember::factory()->create(['meeting_interval_days' => 14]);
    $member2 = TeamMember::factory()->create(['meeting_interval_days' => 14]);
    $meeting = Meeting::factory()->create(['type' => MeetingType::OneOnOne]);
    $meeting->attendees()->attach([$member1->id, $member2->id]);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member1->refresh();
    $member2->refresh();
    expect($member1->next_meeting_date)->toBeNull()
        ->and($member2->next_meeting_date)->toBeNull();
});

test('overwrites existing next_meeting_date with newly calculated date', function () {
    $member = TeamMember::factory()->create([
        'meeting_interval_days' => 14,
        'next_meeting_date' => '2026-01-01',
    ]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::OneOnOne,
        'scheduled_at' => '2026-03-10 10:00:00',
    ]);
    $meeting->attendees()->attach($member->id);

    $event = new MeetingScheduled($meeting);
    (new ScheduleNextMeeting())->handle($event);

    $member->refresh();
    expect($member->next_meeting_date->toDateString())->toBe('2026-03-24');
});

test('handles meeting with no attendees gracefully', function () {
    $meeting = Meeting::factory()->create(['type' => MeetingType::OneOnOne]);

    $event = new MeetingScheduled($meeting);

    expect(fn () => (new ScheduleNextMeeting())->handle($event))->not->toThrow(Throwable::class);
});
