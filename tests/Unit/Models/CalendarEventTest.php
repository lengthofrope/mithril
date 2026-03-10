<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

describe('CalendarEvent model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(CalendarEvent::class)))->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a user', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

            expect($event->user())->toBeInstanceOf(BelongsTo::class)
                ->and($event->user->id)->toBe($user->id);
        });
    });

    describe('casts', function (): void {
        it('casts status to CalendarEventStatus enum', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id' => $user->id,
                'status'  => CalendarEventStatus::Busy,
            ]);

            expect($event->fresh()->status)->toBe(CalendarEventStatus::Busy);
        });

        it('casts status to the correct enum case from raw string value', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id' => $user->id,
                'status'  => 'tentative',
            ]);

            expect($event->fresh()->status)->toBe(CalendarEventStatus::Tentative);
        });

        it('casts is_all_day to boolean true', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'is_all_day' => true,
            ]);

            expect($event->fresh()->is_all_day)->toBeTrue();
        });

        it('casts is_all_day to boolean false', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id'    => $user->id,
                'is_all_day' => false,
            ]);

            expect($event->fresh()->is_all_day)->toBeFalse();
        });

        it('casts is_online_meeting to boolean true', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id'           => $user->id,
                'is_online_meeting' => true,
            ]);

            expect($event->fresh()->is_online_meeting)->toBeTrue();
        });

        it('casts is_online_meeting to boolean false', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id'           => $user->id,
                'is_online_meeting' => false,
            ]);

            expect($event->fresh()->is_online_meeting)->toBeFalse();
        });

        it('casts start_at to a Carbon instance', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

            expect($event->fresh()->start_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts end_at to a Carbon instance', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

            expect($event->fresh()->end_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts synced_at to a Carbon instance', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

            expect($event->fresh()->synced_at)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('startingFrom scope', function (): void {
        it('includes events whose start_at is exactly on the boundary date', function (): void {
            $user      = User::factory()->create();
            $boundary  = now()->startOfDay();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary, 'end_at' => $boundary->copy()->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->startingFrom($boundary)
                ->get();

            expect($result)->toHaveCount(1);
        });

        it('includes events that start after the boundary date', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->startOfDay();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->addHour(), 'end_at' => $boundary->copy()->addHours(2)]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->startingFrom($boundary)
                ->get();

            expect($result)->toHaveCount(1);
        });

        it('excludes events that start before the boundary date', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->startOfDay();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->subSecond(), 'end_at' => $boundary->copy()->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->startingFrom($boundary)
                ->get();

            expect($result)->toHaveCount(0);
        });

        it('returns only events on or after the given date when mixed records exist', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->startOfDay();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->subDay(), 'end_at' => $boundary->copy()->subDay()->addHour()]);
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->addHour(), 'end_at' => $boundary->copy()->addHours(2)]);
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->addDays(2), 'end_at' => $boundary->copy()->addDays(2)->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->startingFrom($boundary)
                ->get();

            expect($result)->toHaveCount(2);
        });
    });

    describe('until scope', function (): void {
        it('includes events whose start_at is exactly on the boundary date', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->endOfWeek();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary, 'end_at' => $boundary->copy()->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->until($boundary)
                ->get();

            expect($result)->toHaveCount(1);
        });

        it('includes events that start before the boundary date', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->endOfWeek();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->subHour(), 'end_at' => $boundary]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->until($boundary)
                ->get();

            expect($result)->toHaveCount(1);
        });

        it('excludes events that start after the boundary date', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->endOfWeek();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->addSecond(), 'end_at' => $boundary->copy()->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->until($boundary)
                ->get();

            expect($result)->toHaveCount(0);
        });

        it('returns only events on or before the given date when mixed records exist', function (): void {
            $user     = User::factory()->create();
            $boundary = now()->endOfWeek();
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->subDays(2), 'end_at' => $boundary->copy()->subDays(2)->addHour()]);
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary, 'end_at' => $boundary->copy()->addHour()]);
            CalendarEvent::factory()->create(['user_id' => $user->id, 'start_at' => $boundary->copy()->addDay(), 'end_at' => $boundary->copy()->addDay()->addHour()]);

            $result = CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->until($boundary)
                ->get();

            expect($result)->toHaveCount(2);
        });
    });

    describe('factory', function (): void {
        it('creates a valid CalendarEvent instance', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

            expect($event)->toBeInstanceOf(CalendarEvent::class)
                ->and($event->id)->toBeInt()
                ->and($event->user_id)->toBe($user->id)
                ->and($event->microsoft_event_id)->toBeString()->not->toBeEmpty()
                ->and($event->subject)->toBeString()->not->toBeEmpty()
                ->and($event->start_at)->toBeInstanceOf(Carbon::class)
                ->and($event->end_at)->toBeInstanceOf(Carbon::class)
                ->and($event->status)->toBeInstanceOf(CalendarEventStatus::class);
        });

        it('creates an event with nullable optional fields', function (): void {
            $user  = User::factory()->create();
            $event = CalendarEvent::factory()->create([
                'user_id'            => $user->id,
                'location'           => null,
                'online_meeting_url' => null,
                'organizer_name'     => null,
                'organizer_email'    => null,
            ]);

            expect($event->location)->toBeNull()
                ->and($event->online_meeting_url)->toBeNull()
                ->and($event->organizer_name)->toBeNull()
                ->and($event->organizer_email)->toBeNull();
        });
    });
});
