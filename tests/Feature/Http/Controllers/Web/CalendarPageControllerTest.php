<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('Calendar page', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('returns 200 for an authenticated user with Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $this->actingAs($user)
            ->get('/calendar')
            ->assertOk();
    });

    it('renders the calendar page view', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $this->actingAs($user)
            ->get('/calendar')
            ->assertViewIs('pages.calendar');
    });

    it('passes calendarEvents to the view', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertViewHas('calendarEvents');
        expect($response->viewData('calendarEvents'))->toHaveCount(1);
    });

    it('passes isMicrosoftConnected as true for connected users', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $response = $this->actingAs($user)->get('/calendar');

        expect($response->viewData('isMicrosoftConnected'))->toBeTrue();
    });

    it('passes userTimezone to the view', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertViewHas('userTimezone');
    });

    it('fetches events for the current week ordered by start time', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $laterEvent = CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(5),
            'end_at' => now()->addHours(6),
            'subject' => 'Later event',
        ]);

        $earlierEvent = CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'Earlier event',
        ]);

        $response = $this->actingAs($user)->get('/calendar');
        $events = $response->viewData('calendarEvents');

        expect($events->first()->subject)->toBe('Earlier event');
        expect($events->last()->subject)->toBe('Later event');
    });

    it('eager-loads links on calendar events', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)->get('/calendar');
        $event = $response->viewData('calendarEvents')->first();

        expect($event->relationLoaded('links'))->toBeTrue();
    });

    it('redirects unauthenticated users to login', function (): void {
        $this->get('/calendar')
            ->assertRedirect('/login');
    });
});
