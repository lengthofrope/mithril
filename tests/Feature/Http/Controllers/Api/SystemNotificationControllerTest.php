<?php

declare(strict_types=1);

use App\Models\SystemNotification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

describe('system notification dismiss', function (): void {
    it('dismisses a notification for the authenticated user', function (): void {
        $notification = SystemNotification::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('success', true);

        expect($notification->isDismissedBy($this->user))->toBeTrue();
    });

    it('does not affect other users when dismissing', function (): void {
        $otherUser = User::factory()->create();
        $notification = SystemNotification::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss")
            ->assertOk();

        expect($notification->isDismissedBy($this->user))->toBeTrue();
        expect($notification->isDismissedBy($otherUser))->toBeFalse();
    });

    it('is idempotent when dismissing twice', function (): void {
        $notification = SystemNotification::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss")
            ->assertOk();

        $this->actingAs($this->user)
            ->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss")
            ->assertOk();

        expect($notification->dismissals()->where('user_id', $this->user->id)->count())->toBe(1);
    });

    it('requires authentication', function (): void {
        $notification = SystemNotification::factory()->create(['is_active' => true]);

        $this->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss")
            ->assertUnauthorized();
    });

    it('returns 404 for non-existent notification', function (): void {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/system-notifications/99999/dismiss')
            ->assertNotFound();
    });
});
