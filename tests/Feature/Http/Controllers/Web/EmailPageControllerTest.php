<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders mail page for authenticated user with microsoft connection', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)->get('/mail');

    $response->assertOk();
    $response->assertViewIs('pages.mail');
    $response->assertViewHas('isMicrosoftConnected', true);
});

it('renders mail page for authenticated user without microsoft connection', function (): void {
    $user = User::factory()->create(['microsoft_id' => null]);

    $response = $this->actingAs($user)->get('/mail');

    $response->assertOk();
    $response->assertViewIs('pages.mail');
    $response->assertViewHas('isMicrosoftConnected', false);
});

it('redirects unauthenticated users to login', function (): void {
    $response = $this->get('/mail');

    $response->assertRedirect('/login');
});

it('has named route mail.index', function (): void {
    expect(route('mail.index'))->toContain('/mail');
});
