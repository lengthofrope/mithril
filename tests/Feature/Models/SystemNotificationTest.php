<?php

declare(strict_types=1);

use App\Models\SystemNotification;
use App\Models\User;

describe('SystemNotification model', function (): void {
    it('does not use BelongsToUser scope', function (): void {
        SystemNotification::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser);

        expect(SystemNotification::count())->toBe(1);
    });

    it('casts expires_at to datetime', function (): void {
        $notification = SystemNotification::factory()->create([
            'expires_at' => '2026-12-31 23:59:59',
        ]);

        expect($notification->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('casts is_active to boolean', function (): void {
        $notification = SystemNotification::factory()->create(['is_active' => 1]);

        expect($notification->is_active)->toBeTrue();
    });

    it('scopes to active non-expired notifications', function (): void {
        SystemNotification::factory()->create(['is_active' => true, 'expires_at' => null]);
        SystemNotification::factory()->create(['is_active' => true, 'expires_at' => now()->addDay()]);
        SystemNotification::factory()->create(['is_active' => false]);
        SystemNotification::factory()->create(['is_active' => true, 'expires_at' => now()->subDay()]);

        expect(SystemNotification::active()->count())->toBe(2);
    });

    it('scopes to exclude dismissed for a user', function (): void {
        $user = User::factory()->create();
        $notification1 = SystemNotification::factory()->create(['is_active' => true]);
        $notification2 = SystemNotification::factory()->create(['is_active' => true]);

        $notification1->dismissals()->attach($user->id, ['dismissed_at' => now()]);

        expect(SystemNotification::active()->notDismissedBy($user)->count())->toBe(1);
    });

    it('tracks dismissal per user via pivot', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $notification = SystemNotification::factory()->create(['is_active' => true]);

        $notification->dismissals()->attach($user1->id, ['dismissed_at' => now()]);

        expect($notification->dismissals()->count())->toBe(1);
        expect($notification->isDismissedBy($user1))->toBeTrue();
        expect($notification->isDismissedBy($user2))->toBeFalse();
    });
});
