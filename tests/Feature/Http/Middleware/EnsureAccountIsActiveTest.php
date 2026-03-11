<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

describe('EnsureAccountIsActive middleware', function (): void {
    it('allows active users to access authenticated routes', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
    });

    it('redirects disabled users to login with error', function (): void {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
    });

    it('logs out disabled users mid-session', function (): void {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/');

        expect(Auth::check())->toBeFalse();
    });

    it('allows disabled users to reach the logout route', function (): void {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
    });
});
