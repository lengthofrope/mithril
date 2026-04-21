<?php

declare(strict_types=1);

use App\Models\User;

describe('User model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of activity_sort_order', function (): void {
            $user = User::factory()->create(['activity_sort_order' => 'asc']);

            $user->fill(['activity_sort_order' => 'desc']);

            expect($user->activity_sort_order)->toBe('desc');
        });

        it('persists activity_sort_order when saved', function (): void {
            $user = User::factory()->create();

            $user->update(['activity_sort_order' => 'desc']);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'activity_sort_order' => 'desc',
            ]);
        });
    });
});
