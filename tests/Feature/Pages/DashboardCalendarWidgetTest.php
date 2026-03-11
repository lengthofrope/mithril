<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('Dashboard calendar widget', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('limits calendar events to 3 on the dashboard', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        CalendarEvent::factory()->count(5)->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('calendarEvents'))->toHaveCount(3);
    });

    it('returns the next 3 events ordered by start time', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
            'subject' => 'Third',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'First',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(3),
            'subject' => 'Second',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(4),
            'end_at' => now()->addHours(5),
            'subject' => 'Fourth - should not appear',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(3);
        expect($events[0]->subject)->toBe('First');
        expect($events[1]->subject)->toBe('Second');
        expect($events[2]->subject)->toBe('Third');
    });

    it('does not pass calendarEvents when user has no Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('calendarEvents'))->toBeNull();
    });

    it('does not show the calendar widget when user has no Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);

        $this->actingAs($user)
            ->get('/')
            ->assertDontSee('Connect your Office 365 account')
            ->assertDontSee('Calendar');
    });

    it('shows calendar widget when user has Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Upcoming');
    });

    it('excludes events that have already ended', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        // Event that ended 1 hour ago — should NOT appear
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'subject' => 'Finished meeting',
        ]);

        // Event starting in 1 hour — should appear
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'Future meeting',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(1);
        expect($events[0]->subject)->toBe('Future meeting');
    });

    it('includes events that are currently happening', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        // Event that started 30 min ago and ends in 30 min — should appear
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subMinutes(30),
            'end_at' => now()->addMinutes(30),
            'subject' => 'Ongoing meeting',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(1);
        expect($events[0]->subject)->toBe('Ongoing meeting');
    });

    it('includes events from tomorrow when today has fewer than 3', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'Today event',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addDay()->startOfDay()->addHours(10),
            'end_at' => now()->addDay()->startOfDay()->addHours(11),
            'subject' => 'Tomorrow event',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(2);
        expect($events[0]->subject)->toBe('Today event');
        expect($events[1]->subject)->toBe('Tomorrow event');
    });
});
