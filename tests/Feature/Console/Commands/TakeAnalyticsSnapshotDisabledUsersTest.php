<?php

declare(strict_types=1);

use App\Models\AnalyticsSnapshot;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('analytics:snapshot with disabled users', function (): void {
    it('skips disabled users', function (): void {
        $active = User::factory()->create();
        $disabled = User::factory()->create(['is_active' => false]);

        $this->artisan('analytics:snapshot');

        expect(AnalyticsSnapshot::where('user_id', $active->id)->count())->toBe(9);
        expect(AnalyticsSnapshot::where('user_id', $disabled->id)->count())->toBe(0);
    });

    it('resumes snapshots for re-enabled user', function (): void {
        $user = User::factory()->create(['is_active' => false]);

        $this->artisan('analytics:snapshot');
        expect(AnalyticsSnapshot::where('user_id', $user->id)->count())->toBe(0);

        $user->update(['is_active' => true]);

        $this->artisan('analytics:snapshot');
        expect(AnalyticsSnapshot::where('user_id', $user->id)->count())->toBe(9);
    });
});
