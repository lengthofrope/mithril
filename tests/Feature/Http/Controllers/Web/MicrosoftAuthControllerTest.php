<?php

declare(strict_types=1);

use App\DataTransferObjects\TokenResponse;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Carbon\Carbon;

test('redirect requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $this->get(route('microsoft.redirect'))
        ->assertRedirect('/login');
});

test('redirect starts OAuth flow and stores state in session', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    config([
        'microsoft.authority'     => 'https://login.microsoftonline.com/',
        'microsoft.tenant_id'     => 'test-tenant',
        'microsoft.client_id'     => 'test-client-id',
        'microsoft.redirect_uri'  => 'http://localhost/auth/microsoft/callback',
        'microsoft.scopes'        => ['User.Read', 'offline_access'],
    ]);

    $response = $this->actingAs($user)->get(route('microsoft.redirect'));

    $response->assertRedirect();

    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('login.microsoftonline.com');

    $this->assertNotNull(session('microsoft_oauth_state'));
    expect(strlen(session('microsoft_oauth_state')))->toBeGreaterThan(0);
});

test('callback rejects mismatched state', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['microsoft_oauth_state' => 'correct-state'])
        ->get(route('microsoft.callback', [
            'state' => 'wrong-state',
            'code'  => 'some-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('callback rejects when error param is present', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['microsoft_oauth_state' => 'valid-state'])
        ->get(route('microsoft.callback', [
            'error'             => 'access_denied',
            'error_description' => 'The user declined to authorize.',
            'state'             => 'valid-state',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('callback exchanges code for tokens and updates user', function () {
    /** @var \Tests\TestCase $this */
    $user      = User::factory()->create();
    $expiresAt = Carbon::now()->addHour();

    $tokenResponse = new TokenResponse(
        accessToken:  'test-access-token',
        refreshToken: 'test-refresh-token',
        expiresAt:    $expiresAt,
        microsoftId:  'ms-object-id-abc',
        email:        'connected@example.com',
    );

    $mock = Mockery::mock(MicrosoftGraphService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->with('valid-auth-code')
        ->andReturn($tokenResponse);

    $this->app->instance(MicrosoftGraphService::class, $mock);

    $response = $this->actingAs($user)
        ->withSession(['microsoft_oauth_state' => 'valid-state'])
        ->get(route('microsoft.callback', [
            'state' => 'valid-state',
            'code'  => 'valid-auth-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->microsoft_id)->toBe('ms-object-id-abc')
        ->and($user->microsoft_email)->toBe('connected@example.com');

    $this->assertDatabaseHas('users', [
        'id'            => $user->id,
        'microsoft_id'  => 'ms-object-id-abc',
        'microsoft_email' => 'connected@example.com',
    ]);
});

test('callback redirects with error when token exchange throws', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $mock = Mockery::mock(MicrosoftGraphService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->andThrow(new RuntimeException('Token exchange failed'));

    $this->app->instance(MicrosoftGraphService::class, $mock);

    $response = $this->actingAs($user)
        ->withSession(['microsoft_oauth_state' => 'valid-state'])
        ->get(route('microsoft.callback', [
            'state' => 'valid-state',
            'code'  => 'bad-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('disconnect requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $this->delete(route('microsoft.disconnect'))
        ->assertRedirect('/login');
});

test('disconnect clears all microsoft credentials from user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'microsoft_id'    => 'ms-object-id-abc',
        'microsoft_email' => 'connected@example.com',
    ]);

    $mock = Mockery::mock(MicrosoftGraphService::class);
    $mock->shouldReceive('revokeAccess')
        ->once()
        ->andReturnUsing(function (User $resolvedUser): void {
            $resolvedUser->microsoft_id               = null;
            $resolvedUser->microsoft_email            = null;
            $resolvedUser->microsoft_access_token     = null;
            $resolvedUser->microsoft_refresh_token    = null;
            $resolvedUser->microsoft_token_expires_at = null;
            $resolvedUser->save();
        });

    $this->app->instance(MicrosoftGraphService::class, $mock);

    $response = $this->actingAs($user)->delete(route('microsoft.disconnect'));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->microsoft_id)->toBeNull()
        ->and($user->microsoft_email)->toBeNull()
        ->and($user->microsoft_access_token)->toBeNull()
        ->and($user->microsoft_refresh_token)->toBeNull()
        ->and($user->microsoft_token_expires_at)->toBeNull();
});
