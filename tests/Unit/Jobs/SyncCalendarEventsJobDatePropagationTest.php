<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Carbon;

describe('SyncCalendarEventsJob date propagation', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));

        $this->user = User::factory()->create([
            'microsoft_id'               => 'ms-date-prop',
            'microsoft_email'            => 'user@example.com',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('updates a linked meeting scheduled_at when the calendar event date changes', function (): void {
        $originalDate = now()->addDays(2);
        $newDate      = now()->addDays(5);

        $meeting = Meeting::factory()->create([
            'user_id'      => $this->user->id,
            'scheduled_at' => $originalDate,
        ]);

        $calendarEvent = CalendarEvent::factory()->create([
            'user_id'            => $this->user->id,
            'microsoft_event_id' => 'event-date-change',
            'start_at'           => $originalDate,
            'end_at'             => $originalDate->copy()->addHour(),
        ]);

        CalendarEventLink::factory()->forMeeting($meeting)->create([
            'calendar_event_id' => $calendarEvent->id,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-date-change',
                    'subject'            => 'Moved meeting',
                    'start_at'           => $newDate,
                    'end_at'             => $newDate->copy()->addHour(),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => null,
                    'organizer_email'    => null,
                ],
            ]));

        (new SyncCalendarEventsJob($this->user))->handle($mock);

        $meeting->refresh();

        expect($meeting->scheduled_at->toDateString())->toBe($newDate->toDateString());
    });

    it('does not update a linked meeting when the calendar event date has not changed', function (): void {
        $sameDate = now()->addDays(2);

        $meeting = Meeting::factory()->create([
            'user_id'      => $this->user->id,
            'scheduled_at' => $sameDate,
        ]);

        $calendarEvent = CalendarEvent::factory()->create([
            'user_id'            => $this->user->id,
            'microsoft_event_id' => 'event-no-change',
            'start_at'           => $sameDate,
            'end_at'             => $sameDate->copy()->addHour(),
        ]);

        CalendarEventLink::factory()->forMeeting($meeting)->create([
            'calendar_event_id' => $calendarEvent->id,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-no-change',
                    'subject'            => 'Same date meeting',
                    'start_at'           => $sameDate,
                    'end_at'             => $sameDate->copy()->addHour(),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => null,
                    'organizer_email'    => null,
                ],
            ]));

        $originalUpdatedAt = $meeting->updated_at;

        Carbon::setTestNow(now()->addSecond());

        (new SyncCalendarEventsJob($this->user))->handle($mock);

        $meeting->refresh();

        expect($meeting->scheduled_at->toDateString())->toBe($sameDate->toDateString())
            ->and($meeting->updated_at->toDateTimeString())->toBe($originalUpdatedAt->toDateTimeString());
    });

    it('does not affect meetings that are not linked to any calendar event', function (): void {
        $meetingDate = now()->addDays(3);

        $unlinkedMeeting = Meeting::factory()->create([
            'user_id'      => $this->user->id,
            'scheduled_at' => $meetingDate,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-unrelated',
                    'subject'            => 'Unrelated event',
                    'start_at'           => now()->addDays(1),
                    'end_at'             => now()->addDays(1)->addHour(),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => null,
                    'organizer_email'    => null,
                ],
            ]));

        (new SyncCalendarEventsJob($this->user))->handle($mock);

        $unlinkedMeeting->refresh();

        expect($unlinkedMeeting->scheduled_at->toDateString())->toBe($meetingDate->toDateString());
    });

    it('only updates meetings belonging to the same user as the calendar event', function (): void {
        $otherUser = User::factory()->create();
        $newDate   = now()->addDays(5);

        $otherUserMeeting = Meeting::factory()->create([
            'user_id'      => $otherUser->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        $calendarEvent = CalendarEvent::factory()->create([
            'user_id'            => $this->user->id,
            'microsoft_event_id' => 'event-cross-user',
            'start_at'           => now()->addDays(2),
            'end_at'             => now()->addDays(2)->addHour(),
        ]);

        // Manually create a link pointing to the other user's meeting
        // (should not happen normally, but we must guard against it)
        CalendarEventLink::factory()->forMeeting($otherUserMeeting)->create([
            'calendar_event_id' => $calendarEvent->id,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-cross-user',
                    'subject'            => 'Cross-user event',
                    'start_at'           => $newDate,
                    'end_at'             => $newDate->copy()->addHour(),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => null,
                    'organizer_email'    => null,
                ],
            ]));

        (new SyncCalendarEventsJob($this->user))->handle($mock);

        $otherUserMeeting->refresh();

        expect($otherUserMeeting->scheduled_at->toDateString())->not->toBe($newDate->toDateString());
    });

    it('retains the meeting date when its linked calendar event is deleted from the API', function (): void {
        $meetingDate = now()->addDays(2);

        $meeting = Meeting::factory()->create([
            'user_id'      => $this->user->id,
            'scheduled_at' => $meetingDate,
        ]);

        $calendarEvent = CalendarEvent::factory()->create([
            'user_id'            => $this->user->id,
            'microsoft_event_id' => 'event-to-be-deleted',
            'start_at'           => $meetingDate,
            'end_at'             => $meetingDate->copy()->addHour(),
        ]);

        CalendarEventLink::factory()->forMeeting($meeting)->create([
            'calendar_event_id' => $calendarEvent->id,
        ]);

        // Return empty response so the event gets deleted
        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([]));

        (new SyncCalendarEventsJob($this->user))->handle($mock);

        $meeting->refresh();

        expect($meeting->scheduled_at->toDateString())->toBe($meetingDate->toDateString());
    });
});
