<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('Calendar single-digit day grouping (W-1 regression)', function (): void {
    beforeEach(function (): void {
        // Monday 2026-04-06 — the week contains several single-digit days (6, 7, 8, 9).
        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('groups an event on a single-digit day using the "j" format that matches the day seed', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);

        // Event on Wednesday April 8 (single-digit day, neither today nor tomorrow).
        CalendarEvent::factory()->create([
            'user_id'  => $user->id,
            'subject'  => 'Wednesday retrospective',
            'start_at' => Carbon::parse('2026-04-08 10:00:00'),
            'end_at'   => Carbon::parse('2026-04-08 11:00:00'),
            'status'   => CalendarEventStatus::Busy,
        ]);

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertOk()
            ->assertSee('Wednesday, 8 April')
            ->assertSee('Wednesday retrospective');
    });
});
