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

    it('shows all calendar events without a 3-item limit', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        // Create 5 events with staggered times
        for ($i = 1; $i <= 5; $i++) {
            CalendarEvent::factory()->create([
                'user_id' => $user->id,
                'start_at' => now()->addHours($i),
                'end_at' => now()->addHours($i + 1),
                'subject' => "Event {$i}",
            ]);
        }

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('calendarEvents'))->toHaveCount(5);
    });

    it('returns all events ordered by start time', function (): void {
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
            'subject' => 'Fourth - now appears',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(4);
        expect($events[0]->subject)->toBe('First');
        expect($events[1]->subject)->toBe('Second');
        expect($events[2]->subject)->toBe('Third');
        expect($events[3]->subject)->toBe('Fourth - now appears');
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

    it('shows all events when a single day has 3+ events', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-123',
            'dashboard_upcoming_meetings' => 10,
        ]);

        // Create 4 events today
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'Today event 1',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(3),
            'subject' => 'Today event 2',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
            'subject' => 'Today event 3',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(4),
            'end_at' => now()->addHours(5),
            'subject' => 'Today event 4',
        ]);

        // Create 2 events tomorrow
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addDay()->startOfDay()->addHours(10),
            'end_at' => now()->addDay()->startOfDay()->addHours(11),
            'subject' => 'Tomorrow event 1',
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addDay()->startOfDay()->addHours(14),
            'end_at' => now()->addDay()->startOfDay()->addHours(15),
            'subject' => 'Tomorrow event 2',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(6);
        expect($events[0]->subject)->toBe('Today event 1');
        expect($events[1]->subject)->toBe('Today event 2');
        expect($events[2]->subject)->toBe('Today event 3');
        expect($events[3]->subject)->toBe('Today event 4');
        expect($events[4]->subject)->toBe('Tomorrow event 1');
        expect($events[5]->subject)->toBe('Tomorrow event 2');
    });

    // Phase 6: respect user limit on timed events, exempt all-day, insert leaf separator.
    // The limit is a total cap on TIMED events across the week window; all-day events are never dropped.

    it('applies the user limit to timed events while exempting all-day events (Phase 6)', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-123',
            'dashboard_upcoming_meetings' => 3,
        ]);

        // 5 timed events today (only 3 should fit)
        for ($i = 1; $i <= 5; $i++) {
            CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->addHours($i),
                'end_at'     => now()->addHours($i)->addMinutes(30),
                'is_all_day' => false,
                'subject'    => "Timed today {$i}",
            ]);
        }

        // 2 all-day events today (always included, do not consume slots)
        for ($i = 1; $i <= 2; $i++) {
            CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->startOfDay(),
                'end_at'     => now()->endOfDay(),
                'is_all_day' => true,
                'subject'    => "All day today {$i}",
            ]);
        }

        // 4 timed events tomorrow (limit already exhausted; these should NOT appear)
        for ($i = 1; $i <= 4; $i++) {
            CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->addDay()->startOfDay()->addHours(8 + $i),
                'end_at'     => now()->addDay()->startOfDay()->addHours(8 + $i)->addMinutes(30),
                'is_all_day' => false,
                'subject'    => "Timed tomorrow {$i}",
            ]);
        }

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        $timed = $events->where('is_all_day', false);
        $allDay = $events->where('is_all_day', true);

        expect($timed)->toHaveCount(3);
        expect($allDay)->toHaveCount(2);
        expect($timed->pluck('subject')->all())->toBe([
            'Timed today 1',
            'Timed today 2',
            'Timed today 3',
        ]);
    });

    it('never drops all-day events even when the timed limit is small (Phase 6)', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-123',
            'dashboard_upcoming_meetings' => 2,
        ]);

        CalendarEvent::factory()->create([
            'user_id'    => $user->id,
            'start_at'   => now()->addHour(),
            'end_at'     => now()->addHours(2),
            'is_all_day' => false,
            'subject'    => 'Timed today',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->startOfDay(),
                'end_at'     => now()->endOfDay(),
                'is_all_day' => true,
                'subject'    => "All day {$i}",
            ]);
        }

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events->where('is_all_day', false))->toHaveCount(1);
        expect($events->where('is_all_day', true))->toHaveCount(3);
    });

    it('renders the leaf divider once between today and the first non-today day (Phase 6)', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-123',
            'dashboard_upcoming_meetings' => 5,
        ]);

        $today = collect();
        for ($i = 1; $i <= 2; $i++) {
            $today->push(CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->addHours($i),
                'end_at'     => now()->addHours($i)->addMinutes(30),
                'is_all_day' => false,
                'subject'    => "Today {$i}",
            ]));
        }

        $tomorrow = collect();
        for ($i = 1; $i <= 3; $i++) {
            $tomorrow->push(CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->addDay()->startOfDay()->addHours(8 + $i),
                'end_at'     => now()->addDay()->startOfDay()->addHours(8 + $i)->addMinutes(30),
                'is_all_day' => false,
                'subject'    => "Tomorrow {$i}",
            ]));
        }

        $events = $today->concat($tomorrow);
        $html = view('components.tl.calendar-upcoming', [
            'events' => $events,
            'timezone' => 'Europe/Amsterdam',
        ])->render();

        expect(substr_count($html, 'elvish-divider-leaf'))->toBe(1);
    });

    it('does not render the leaf divider when today has no events (Phase 6)', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-123',
            'dashboard_upcoming_meetings' => 5,
        ]);

        $tomorrow = collect();
        for ($i = 1; $i <= 3; $i++) {
            $tomorrow->push(CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'start_at'   => now()->addDay()->startOfDay()->addHours(8 + $i),
                'end_at'     => now()->addDay()->startOfDay()->addHours(8 + $i)->addMinutes(30),
                'is_all_day' => false,
                'subject'    => "Tomorrow {$i}",
            ]));
        }

        $html = view('components.tl.calendar-upcoming', [
            'events' => $tomorrow,
            'timezone' => 'Europe/Amsterdam',
        ])->render();

        expect(substr_count($html, 'elvish-divider-leaf'))->toBe(0);
    });

    it('excludes events that ended earlier today', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        // Event that ended 2 hours ago (same day, but past) — should NOT appear
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHours(2),
            'subject' => 'Past event today',
        ]);

        // Event starting in 1 hour — should appear
        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'subject' => 'Future event today',
        ]);

        $response = $this->actingAs($user)->get('/');
        $events = $response->viewData('calendarEvents');

        expect($events)->toHaveCount(1);
        expect($events[0]->subject)->toBe('Future event today');
    });
});
