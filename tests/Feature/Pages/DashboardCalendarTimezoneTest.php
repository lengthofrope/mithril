<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('Dashboard calendar timezone display', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'UTC'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('displays calendar event times converted to the user timezone', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-tz',
            'timezone'     => 'Europe/Amsterdam',
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Timezone test meeting',
            'start_at' => '2026-03-10 13:00:00',
            'end_at'   => '2026-03-10 14:00:00',
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('14:00')
            ->assertSee('15:00');
    });

    it('displays UTC times when user has no timezone set', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-no-tz',
            'timezone'     => null,
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'UTC fallback meeting',
            'start_at' => '2026-03-10 13:00:00',
            'end_at'   => '2026-03-10 14:00:00',
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('14:00')
            ->assertSee('15:00');
    });

    it('groups events by day in the user timezone', function (): void {
        $user = User::factory()->create([
            'microsoft_id' => 'ms-tz-group',
            'timezone'     => 'Europe/Amsterdam',
        ]);

        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Late night meeting',
            'start_at' => '2026-03-10 23:30:00',
            'end_at'   => '2026-03-11 00:30:00',
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('Tomorrow');
    });

    it('uses user timezone for greeting resolution', function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 06:00:00', 'UTC'));

        $user = User::factory()->create([
            'microsoft_id' => 'ms-greet',
            'timezone'     => 'Asia/Tokyo',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('Good afternoon');
    });
});
