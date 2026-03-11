<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

describe('login with disabled account', function (): void {
    it('rejects login for disabled user with correct credentials', function (): void {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        expect(Auth::check())->toBeFalse();
    });

    it('allows login for active user with correct credentials', function (): void {
        User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    });

    it('uses generic error message for disabled account', function (): void {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'Your account has been disabled.']);
    });

    it('preserves 2fa state when user is disabled and re-enabled', function (): void {
        $user = User::factory()->create([
            'email' => 'twofa@example.com',
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('TESTSECRET'),
            'two_factor_confirmed_at' => now(),
            'is_active' => false,
        ]);

        $user->update(['is_active' => true]);
        $user->refresh();

        expect($user->hasTwoFactorEnabled())->toBeTrue();
    });
});
