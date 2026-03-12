<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('updateSidebarCollapsed saves collapsed true', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['sidebar_collapsed' => false]);

    $response = $this->actingAs($user)->patchJson('/settings/sidebar-collapsed', [
        'sidebar_collapsed' => true,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    $user->refresh();
    expect($user->sidebar_collapsed)->toBeTrue();
});

test('updateSidebarCollapsed saves collapsed false', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['sidebar_collapsed' => true]);

    $response = $this->actingAs($user)->patchJson('/settings/sidebar-collapsed', [
        'sidebar_collapsed' => false,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    $user->refresh();
    expect($user->sidebar_collapsed)->toBeFalse();
});

test('updateSidebarCollapsed rejects non-boolean values', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/settings/sidebar-collapsed', [
        'sidebar_collapsed' => 'yes',
    ]);

    $response->assertUnprocessable();
});

test('updateSidebarCollapsed requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->patchJson('/settings/sidebar-collapsed', [
        'sidebar_collapsed' => true,
    ]);

    $response->assertUnauthorized();
});

test('sidebar_collapsed defaults to false for new users', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    expect((bool) $user->sidebar_collapsed)->toBeFalse();
});

test('sidebar collapsed preference is passed to layout view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['sidebar_collapsed' => true]);

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertSee("sidebarCollapsed: true", false);
});
