<?php

declare(strict_types=1);

use App\Events\MeetingScheduled;
use App\Models\Meeting;

test('meeting scheduled event carries the meeting model', function () {
    $meeting = Meeting::factory()->create();

    $event = new MeetingScheduled($meeting);

    expect($event->meeting)->toBeInstanceOf(Meeting::class);
    expect($event->meeting->id)->toBe($meeting->id);
});

test('meeting scheduled event meeting property matches the given instance', function () {
    $meeting = Meeting::factory()->create(['scheduled_at' => '2026-04-01 10:00:00']);

    $event = new MeetingScheduled($meeting);

    expect($event->meeting->scheduled_at->format('Y-m-d'))->toBe('2026-04-01');
});

test('meeting scheduled event property is readonly', function () {
    $meeting = Meeting::factory()->create();

    $event = new MeetingScheduled($meeting);

    expect(fn () => $event->meeting = Meeting::factory()->create())->toThrow(Error::class);
});
