<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Carbon;

describe('SyncCalendarEventsJob', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('creates a new calendar event when none exists for the microsoft_event_id', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-123',
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
                    'microsoft_event_id' => 'event-1',
                    'subject'            => 'Team standup',
                    'start_at'           => now()->addHour(),
                    'end_at'             => now()->addHours(2),
                    'is_all_day'         => false,
                    'location'           => 'Room 1',
                    'status'             => 'busy',
                    'is_online_meeting'  => true,
                    'online_meeting_url' => 'https://teams.microsoft.com/meet/123',
                    'organizer_name'     => 'Jane Doe',
                    'organizer_email'    => 'jane@example.com',
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-1')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->subject)->toBe('Team standup')
            ->and($event->location)->toBe('Room 1')
            ->and($event->is_online_meeting)->toBeTrue()
            ->and($event->organizer_name)->toBe('Jane Doe')
            ->and($event->organizer_email)->toBe('jane@example.com');
    });

    it('updates an existing event when it already exists for the microsoft_event_id', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-456',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->create([
            'user_id'            => $user->id,
            'microsoft_event_id' => 'event-existing',
            'subject'            => 'Old subject',
            'start_at'           => now()->addHour(),
            'end_at'             => now()->addHours(2),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-existing',
                    'subject'            => 'Updated subject',
                    'start_at'           => now()->addHour(),
                    'end_at'             => now()->addHours(2),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'busy',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => 'Alice',
                    'organizer_email'    => 'alice@example.com',
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $count = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-existing')
            ->count();

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-existing')
            ->first();

        expect($count)->toBe(1)
            ->and($event->subject)->toBe('Updated subject');
    });

    it('deletes stale events that exist in the database but are absent from the API response', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-789',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->create([
            'user_id'            => $user->id,
            'microsoft_event_id' => 'event-stale',
            'start_at'           => now()->addHour(),
            'end_at'             => now()->addHours(2),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_event_id' => 'event-new',
                    'subject'            => 'New event',
                    'start_at'           => now()->addHours(3),
                    'end_at'             => now()->addHours(4),
                    'is_all_day'         => false,
                    'location'           => null,
                    'status'             => 'free',
                    'is_online_meeting'  => false,
                    'online_meeting_url' => null,
                    'organizer_name'     => null,
                    'organizer_email'    => null,
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $staleExists = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-stale')
            ->exists();

        $newExists = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('microsoft_event_id', 'event-new')
            ->exists();

        expect($staleExists)->toBeFalse()
            ->and($newExists)->toBeTrue();
    });

    it('handles an empty API response gracefully by deleting all synced window events', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-empty',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->create([
            'user_id'            => $user->id,
            'microsoft_event_id' => 'event-to-delete',
            'start_at'           => now()->addHour(),
            'end_at'             => now()->addHours(2),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($user))->handle($mock);

        $remaining = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->count();

        expect($remaining)->toBe(0);
    });

    it('does not re-throw when the Graph API throws and the user has no Microsoft connection', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => null,
            'microsoft_access_token'     => null,
            'microsoft_refresh_token'    => null,
            'microsoft_token_expires_at' => null,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andThrow(new RuntimeException('Consent revoked'));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        expect(fn () => (new SyncCalendarEventsJob($user))->handle($mock))->not->toThrow(RuntimeException::class);
    });

    it('re-throws the exception when the Graph API throws and the user still has a Microsoft connection', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-still-connected',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andThrow(new RuntimeException('Transient failure'));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        expect(fn () => (new SyncCalendarEventsJob($user))->handle($mock))->toThrow(RuntimeException::class, 'Transient failure');
    });

    it('does not touch calendar events of other users', function (): void {
        $userA = User::factory()->create([
            'microsoft_id'               => 'ms-user-a',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $userB = User::factory()->create(['microsoft_id' => null]);

        CalendarEvent::factory()->create([
            'user_id'            => $userB->id,
            'microsoft_event_id' => 'event-other-user',
            'start_at'           => now()->addHour(),
            'end_at'             => now()->addHours(2),
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getMyCalendarEvents')
            ->once()
            ->andReturn(collect([]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncCalendarEventsJob($userA))->handle($mock);

        $otherUserEventStillExists = CalendarEvent::withoutGlobalScopes()
            ->where('user_id', $userB->id)
            ->where('microsoft_event_id', 'event-other-user')
            ->exists();

        expect($otherUserEventStillExists)->toBeTrue();
    });
});
