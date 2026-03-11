<?php

declare(strict_types=1);

use App\Models\User;

describe('user:enable command', function (): void {
    it('enables a disabled user', function (): void {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'is_active' => false,
        ]);

        $this->artisan('user:enable', ['email' => 'john@example.com'])
            ->assertExitCode(0);

        $user->refresh();
        expect($user->is_active)->toBeTrue();
    });

    it('shows error for non-existent email', function (): void {
        $this->artisan('user:enable', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    });

    it('shows info message when user is already active', function (): void {
        User::factory()->create(['email' => 'john@example.com']);

        $this->artisan('user:enable', ['email' => 'john@example.com'])
            ->assertExitCode(0);
    });
});
