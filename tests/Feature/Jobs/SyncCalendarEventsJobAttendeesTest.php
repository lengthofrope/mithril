<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Carbon;

describe('SyncCalendarEventsJob attendee persistence', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('persists attendees JSON when upserting a calendar event', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-attendees-persist',
            'microsoft_email'            => 'user@example.com',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-with-attendees',
                    'subject'            => 'Team planning',
                    'start_at'           => now()->addHour(),
                    'end_at'             => now()->addHours(2),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => 'Alice',
                    'organizer_email'    => 'alice@example.com',
                    'attendees'          => [
                        ['email' => 'bob@example.com', 'name' => 'Bob'],
                        ['email' => 'carol@example.com', 'name' => 'Carol'],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-with-attendees')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->attendees)->toBeArray()
            ->toHaveCount(2)
            ->sequence(
                fn ($item) => $item->toMatchArray(['email' => 'bob@example.com', 'name' => 'Bob']),
                fn ($item) => $item->toMatchArray(['email' => 'carol@example.com', 'name' => 'Carol']),
            );
    });

    it('stores an empty array when the event has no attendees', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-no-attendees-persist',
            'microsoft_email'            => 'user@example.com',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-no-attendees',
                    'subject'            => 'Solo work block',
                    'start_at'           => now()->addHour(),
                    'end_at'             => now()->addHours(2),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => 'Alice',
                    'organizer_email'    => 'alice@example.com',
                    'attendees'          => [],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-no-attendees')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->attendees)->toBeArray()
            ->toBeEmpty();
    });

    it('overwrites existing attendees when the event is updated on re-sync', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-update-attendees',
            'microsoft_email'            => 'user@example.com',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->create([
            'user_id'            => $user->id,
            'microsoft_event_id' => 'event-update-attendees',
            'start_at'           => now()->addHour(),
            'end_at'             => now()->addHours(2),
            'attendees'          => [['email' => 'old@example.com', 'name' => 'Old Person']],
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-update-attendees',
                    'subject'            => 'Updated meeting',
                    'start_at'           => now()->addHour(),
                    'end_at'             => now()->addHours(2),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => 'Alice',
                    'organizer_email'    => 'alice@example.com',
                    'attendees'          => [
                        ['email' => 'new@example.com', 'name' => 'New Person'],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-update-attendees')
            ->first();

        expect($event->attendees)->toHaveCount(1)
            ->and($event->attendees[0]['email'])->toBe('new@example.com');
    });
});
