<?php

declare(strict_types=1);

use App\Models\User;

test('settings page shows connect button when user has no Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['microsoft_id' => null]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Connect Office 365');
});

test('settings page does not show disconnect button when user has no Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['microsoft_id' => null]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertDontSee('Disconnect');
});

test('settings page shows disconnect button when user has a Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'microsoft_id'    => 'ms-object-id-abc',
        'microsoft_email' => 'connected@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Disconnect');
});

test('settings page does not show connect button when user has a Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'microsoft_id'    => 'ms-object-id-abc',
        'microsoft_email' => 'connected@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertDontSee('Connect Office 365');
});

test('settings page shows connected email when user has a Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'microsoft_id'    => 'ms-object-id-abc',
        'microsoft_email' => 'myoffice@company.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('myoffice@company.com');
});

test('settings page shows not connected message when user has no Microsoft connection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['microsoft_id' => null]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Not connected');
});
