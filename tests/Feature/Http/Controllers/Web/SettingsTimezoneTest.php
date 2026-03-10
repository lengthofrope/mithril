<?php

declare(strict_types=1);

use App\Models\User;

describe('Settings timezone', function (): void {
    it('shows the timezone select on the settings page', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Timezone');
    });

    it('defaults to Europe/Amsterdam when no timezone is set', function (): void {
        $user = User::factory()->create(['timezone' => null]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Europe/Amsterdam');
    });

    it('updates the user timezone via PATCH request', function (): void {
        $user = User::factory()->create(['timezone' => 'Europe/Amsterdam']);

        $response = $this->actingAs($user)->patch('/settings/timezone', [
            'timezone' => 'America/New_York',
        ]);

        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'timezone' => 'America/New_York',
        ]);
    });

    it('rejects an invalid timezone value', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings/timezone', [
            'timezone' => 'Not/A/Timezone',
        ]);

        $response->assertUnprocessable();
    });

    it('rejects a missing timezone value', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings/timezone', []);

        $response->assertUnprocessable();
    });

    it('requires authentication to update timezone', function (): void {
        $response = $this->patchJson('/settings/timezone', [
            'timezone' => 'Europe/Amsterdam',
        ]);

        $response->assertUnauthorized();
    });
});
