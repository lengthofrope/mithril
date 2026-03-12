<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('updates email source preferences via PATCH', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)
        ->patchJson(route('settings.updateEmailSources'), [
            'email_source_flagged' => false,
            'email_source_categorized' => true,
            'email_source_category_name' => 'ActionItems',
            'email_source_unread' => true,
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $user->refresh();
    expect($user->email_source_flagged)->toBeFalse();
    expect($user->email_source_categorized)->toBeTrue();
    expect($user->email_source_category_name)->toBe('ActionItems');
    expect($user->email_source_unread)->toBeTrue();
});

it('validates email_source_category_name is a string with max length', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)
        ->patchJson(route('settings.updateEmailSources'), [
            'email_source_category_name' => str_repeat('a', 256),
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email_source_category_name');
});

it('accepts partial updates for email sources', function (): void {
    $user = User::factory()->create([
        'microsoft_id' => 'ms-123',
        'email_source_flagged' => true,
        'email_source_unread' => false,
    ]);

    $response = $this->actingAs($user)
        ->patchJson(route('settings.updateEmailSources'), [
            'email_source_unread' => true,
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->email_source_flagged)->toBeTrue();
    expect($user->email_source_unread)->toBeTrue();
});

it('validates boolean fields are actually booleans', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)
        ->patchJson(route('settings.updateEmailSources'), [
            'email_source_flagged' => 'not-a-boolean',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email_source_flagged');
});

it('requires authentication', function (): void {
    $response = $this->patchJson('/settings/email-sources', [
        'email_source_flagged' => true,
    ]);

    $response->assertUnauthorized();
});

it('shows email sources settings on settings page when microsoft connected', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)->get(route('settings.index'));

    $response->assertOk();
    $response->assertSee('E-mail sources');
});
