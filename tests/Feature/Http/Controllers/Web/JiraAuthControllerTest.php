<?php

declare(strict_types=1);

use App\DataTransferObjects\JiraTokenResponse;
use App\Models\User;
use App\Services\JiraCloudService;
use Carbon\Carbon;

test('redirect requires authentication', function () {
    $this->get(route('jira.redirect'))
        ->assertRedirect('/login');
});

test('redirect starts OAuth flow and stores state in session', function () {
    $user = User::factory()->create();

    config([
        'jira.auth_url'      => 'https://auth.atlassian.com/authorize',
        'jira.client_id'     => 'test-client-id',
        'jira.redirect_uri'  => 'http://localhost/auth/jira/callback',
        'jira.scopes'        => ['read:jira-work', 'read:jira-user', 'offline_access'],
    ]);

    $response = $this->actingAs($user)->get(route('jira.redirect'));

    $response->assertRedirect();

    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('auth.atlassian.com');

    $this->assertNotNull(session('jira_oauth_state'));
    expect(strlen(session('jira_oauth_state')))->toBeGreaterThan(0);
});

test('callback rejects mismatched state', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['jira_oauth_state' => 'correct-state'])
        ->get(route('jira.callback', [
            'state' => 'wrong-state',
            'code'  => 'some-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('callback rejects when error param is present', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['jira_oauth_state' => 'valid-state'])
        ->get(route('jira.callback', [
            'error'             => 'access_denied',
            'error_description' => 'The user declined to authorize.',
            'state'             => 'valid-state',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('callback exchanges code for tokens and updates user', function () {
    $user      = User::factory()->create();
    $expiresAt = Carbon::now()->addHour();

    $tokenResponse = new JiraTokenResponse(
        accessToken:  'test-access-token',
        refreshToken: 'test-refresh-token',
        expiresAt:    $expiresAt,
        cloudId:      'cloud-id-abc',
        siteUrl:      'https://mysite.atlassian.net',
        accountId:    'account-id-xyz',
    );

    $mock = Mockery::mock(JiraCloudService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->with('valid-auth-code')
        ->andReturn($tokenResponse);

    $this->app->instance(JiraCloudService::class, $mock);

    $response = $this->actingAs($user)
        ->withSession(['jira_oauth_state' => 'valid-state'])
        ->get(route('jira.callback', [
            'state' => 'valid-state',
            'code'  => 'valid-auth-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->jira_cloud_id)->toBe('cloud-id-abc')
        ->and($user->jira_account_id)->toBe('account-id-xyz');

    $this->assertDatabaseHas('users', [
        'id'              => $user->id,
        'jira_cloud_id'   => 'cloud-id-abc',
        'jira_account_id' => 'account-id-xyz',
    ]);
});

test('callback redirects with error when token exchange throws', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(JiraCloudService::class);
    $mock->shouldReceive('exchangeCodeForTokens')
        ->once()
        ->andThrow(new RuntimeException('Token exchange failed'));

    $this->app->instance(JiraCloudService::class, $mock);

    $response = $this->actingAs($user)
        ->withSession(['jira_oauth_state' => 'valid-state'])
        ->get(route('jira.callback', [
            'state' => 'valid-state',
            'code'  => 'bad-code',
        ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});

test('disconnect requires authentication', function () {
    $this->delete(route('jira.disconnect'))
        ->assertRedirect('/login');
});

test('disconnect clears all jira credentials from user', function () {
    $user = User::factory()->create([
        'jira_cloud_id'   => 'cloud-id-abc',
        'jira_account_id' => 'account-id-xyz',
    ]);

    $mock = Mockery::mock(JiraCloudService::class);
    $mock->shouldReceive('revokeAccess')
        ->once()
        ->andReturnUsing(function (User $resolvedUser): void {
            $resolvedUser->jira_cloud_id          = null;
            $resolvedUser->jira_site_url          = null;
            $resolvedUser->jira_account_id        = null;
            $resolvedUser->jira_access_token      = null;
            $resolvedUser->jira_refresh_token     = null;
            $resolvedUser->jira_token_expires_at  = null;
            $resolvedUser->save();
        });

    $this->app->instance(JiraCloudService::class, $mock);

    $response = $this->actingAs($user)->delete(route('jira.disconnect'));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->jira_cloud_id)->toBeNull()
        ->and($user->jira_account_id)->toBeNull()
        ->and($user->jira_access_token)->toBeNull()
        ->and($user->jira_refresh_token)->toBeNull()
        ->and($user->jira_token_expires_at)->toBeNull();
});
