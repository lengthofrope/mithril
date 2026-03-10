<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('Dashboard calendar display', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('passes calendarEvents and isMicrosoftConnected variables to the view', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        $response = $this->actingAs($user)->get('/');

        $response->assertViewHas('calendarEvents');
        $response->assertViewHas('isMicrosoftConnected');
    });

    it('sets isMicrosoftConnected to true for a connected user', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-connected']);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('isMicrosoftConnected'))->toBeTrue();
    });

    it('sets isMicrosoftConnected to false for a user without a Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('isMicrosoftConnected'))->toBeFalse();
    });

    it('shows the connect prompt when the user is not connected and has no events', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Connect your Office 365 account');
    });

    it('shows the empty state message when the user is connected but has no events this week', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-no-events']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('No events scheduled for the rest of this week.');
    });

    it('does not show the connect prompt when the user is connected but has no events', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-connected']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('Connect your Office 365 account');
    });

    it('shows calendar event subjects when events exist within the current week', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-with-events']);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Project planning session',
            'start_at' => now()->addHour(),
            'end_at'   => now()->addHours(2),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Project planning session');
    });

    it('shows multiple calendar events when multiple exist within the current week', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-multi-events']);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Morning standup',
            'start_at' => now()->addHour(),
            'end_at'   => now()->addHours(2),
            'status'   => CalendarEventStatus::Busy,
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Afternoon review',
            'start_at' => now()->addHours(5),
            'end_at'   => now()->addHours(6),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Morning standup')
            ->assertSee('Afternoon review');
    });

    it('passes calendar events ordered by start_at ascending', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-order-test']);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Later event',
            'start_at' => now()->addHours(6),
            'end_at'   => now()->addHours(7),
            'status'   => CalendarEventStatus::Busy,
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Earlier event',
            'start_at' => now()->addHour(),
            'end_at'   => now()->addHours(2),
            'status'   => CalendarEventStatus::Busy,
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Middle event',
            'start_at' => now()->addHours(3),
            'end_at'   => now()->addHours(4),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        $events = $response->viewData('calendarEvents');

        expect($events[0]->subject)->toBe('Earlier event')
            ->and($events[1]->subject)->toBe('Middle event')
            ->and($events[2]->subject)->toBe('Later event');
    });

    it('does not include events from other users on the dashboard', function (): void {
        $userA = User::factory()->create(['microsoft_id' => 'ms-user-a']);
        $userB = User::factory()->create(['microsoft_id' => 'ms-user-b']);

        CalendarEvent::factory()->create([
            'user_id'  => $userB->id,
            'subject'  => 'Private event of user B',
            'start_at' => now()->addHour(),
            'end_at'   => now()->addHours(2),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($userA)->get('/');

        expect($response->viewData('calendarEvents'))->toHaveCount(0);
        $response->assertDontSee('Private event of user B');
    });

    it('does not include past events that started before today', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-past']);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Yesterday meeting',
            'start_at' => now()->subDay(),
            'end_at'   => now()->subDay()->addHour(),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('calendarEvents'))->toHaveCount(0);
    });

    it('does not include events that start after the end of the current week', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-future']);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Next week event',
            'start_at' => now()->endOfWeek()->addDay(),
            'end_at'   => now()->endOfWeek()->addDay()->addHour(),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        expect($response->viewData('calendarEvents'))->toHaveCount(0);
    });
});
