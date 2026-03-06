<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WeeklyReflection;
use Illuminate\Support\Carbon;

describe('WeeklyReflection model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of week_start, week_end, summary, and reflection', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-02',
                'week_end' => '2025-06-08',
                'summary' => 'A good week',
                'reflection' => 'Learned a lot',
                'user_id' => $user->id,
            ]);

            expect($reflection->summary)->toBe('A good week')
                ->and($reflection->reflection)->toBe('Learned a lot');
        });

        it('allows null summary and reflection', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-09',
                'week_end' => '2025-06-15',
                'user_id' => $user->id,
            ]);

            expect($reflection->fresh()->summary)->toBeNull()
                ->and($reflection->fresh()->reflection)->toBeNull();
        });
    });

    describe('casts', function (): void {
        it('casts week_start to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-02',
                'week_end' => '2025-06-08',
                'user_id' => $user->id,
            ]);

            expect($reflection->fresh()->week_start)->toBeInstanceOf(Carbon::class);
        });

        it('casts week_end to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-02',
                'week_end' => '2025-06-08',
                'user_id' => $user->id,
            ]);

            expect($reflection->fresh()->week_end)->toBeInstanceOf(Carbon::class);
        });

        it('stores and retrieves week_start as the correct date', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-02',
                'week_end' => '2025-06-08',
                'user_id' => $user->id,
            ]);

            expect($reflection->fresh()->week_start->format('Y-m-d'))->toBe('2025-06-02');
        });

        it('stores and retrieves week_end as the correct date', function (): void {
            $user = User::factory()->create();
            $reflection = WeeklyReflection::create([
                'week_start' => '2025-06-02',
                'week_end' => '2025-06-08',
                'user_id' => $user->id,
            ]);

            expect($reflection->fresh()->week_end->format('Y-m-d'))->toBe('2025-06-08');
        });
    });

    describe('database constraints', function (): void {
        it('enforces unique week_start', function (): void {
            $user = User::factory()->create();
            WeeklyReflection::create(['week_start' => '2025-06-02', 'week_end' => '2025-06-08', 'user_id' => $user->id]);

            expect(fn () => WeeklyReflection::create(['week_start' => '2025-06-02', 'week_end' => '2025-06-08', 'user_id' => $user->id]))
                ->toThrow(\Illuminate\Database\QueryException::class);
        });
    });
});
