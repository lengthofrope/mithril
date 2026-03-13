<?php

declare(strict_types=1);

use App\DataTransferObjects\JiraTokenResponse;
use App\Models\User;
use App\Services\JiraCloudService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'jira.client_id'     => 'test-client-id',
        'jira.client_secret' => 'test-client-secret',
        'jira.redirect_uri'  => 'http://localhost/auth/jira/callback',
        'jira.scopes'        => ['read:jira-work', 'read:jira-user', 'offline_access'],
        'jira.auth_url'      => 'https://auth.atlassian.com/authorize',
        'jira.token_url'     => 'https://auth.atlassian.com/oauth/token',
        'jira.resources_url' => 'https://api.atlassian.com/oauth/token/accessible-resources',
        'jira.api_base_url'  => 'https://api.atlassian.com/ex/jira/',
    ]);
});

test('getAuthorizationUrl builds correct Atlassian OAuth URL', function () {
    $service = new JiraCloudService();
    $url = $service->getAuthorizationUrl('test-state');

    expect($url)
        ->toContain('auth.atlassian.com/authorize')
        ->toContain('client_id=test-client-id')
        ->toContain('state=test-state')
        ->toContain('response_type=code')
        ->toContain('scope=read%3Ajira-work+read%3Ajira-user+offline_access')
        ->toContain('redirect_uri=')
        ->toContain('prompt=consent');
});

test('exchangeCodeForTokens returns JiraTokenResponse with cloud ID', function () {
    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'access_token'  => 'fresh-access-token',
            'refresh_token' => 'fresh-refresh-token',
            'expires_in'    => 3600,
            'scope'         => 'read:jira-work read:jira-user offline_access',
        ], 200),
        'api.atlassian.com/oauth/token/accessible-resources' => Http::response([
            [
                'id'   => 'cloud-id-123',
                'name' => 'My Jira Site',
                'url'  => 'https://mysite.atlassian.net',
            ],
        ], 200),
        'api.atlassian.com/me' => Http::response([
            'account_id' => 'account-abc-123',
            'email'      => 'user@example.com',
            'name'       => 'Test User',
        ], 200),
    ]);

    $service  = new JiraCloudService();
    $response = $service->exchangeCodeForTokens('valid-auth-code');

    expect($response)
        ->toBeInstanceOf(JiraTokenResponse::class)
        ->and($response->accessToken)->toBe('fresh-access-token')
        ->and($response->refreshToken)->toBe('fresh-refresh-token')
        ->and($response->cloudId)->toBe('cloud-id-123')
        ->and($response->accountId)->toBe('account-abc-123')
        ->and($response->expiresAt)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

test('exchangeCodeForTokens throws when token request fails', function () {
    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'error'             => 'invalid_grant',
            'error_description' => 'Authorization code expired.',
        ], 400),
    ]);

    $service = new JiraCloudService();
    $service->exchangeCodeForTokens('expired-code');
})->throws(RuntimeException::class);

test('exchangeCodeForTokens throws when no accessible resources', function () {
    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'access_token'  => 'token',
            'refresh_token' => 'refresh',
            'expires_in'    => 3600,
        ], 200),
        'api.atlassian.com/oauth/token/accessible-resources' => Http::response([], 200),
    ]);

    $service = new JiraCloudService();
    $service->exchangeCodeForTokens('valid-code');
})->throws(RuntimeException::class);

test('refreshAccessToken updates user tokens', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-id-123',
        'jira_account_id'        => 'account-abc',
        'jira_access_token'      => 'old-access-token',
        'jira_refresh_token'     => 'old-refresh-token',
        'jira_token_expires_at'  => now()->subHour(),
    ]);

    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'access_token'  => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in'    => 3600,
        ], 200),
    ]);

    $service = new JiraCloudService();
    $service->refreshAccessToken($user);

    $user->refresh();
    expect($user->jira_access_token)->toBe('new-access-token')
        ->and($user->jira_refresh_token)->toBe('new-refresh-token')
        ->and($user->jira_token_expires_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

test('refreshAccessToken clears credentials on failure', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-id-123',
        'jira_account_id'        => 'account-abc',
        'jira_access_token'      => 'old-token',
        'jira_refresh_token'     => 'old-refresh',
        'jira_token_expires_at'  => now()->subHour(),
    ]);

    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'error' => 'invalid_grant',
        ], 400),
    ]);

    $service = new JiraCloudService();

    try {
        $service->refreshAccessToken($user);
    } catch (RuntimeException) {
        // expected
    }

    $user->refresh();
    expect($user->jira_cloud_id)->toBeNull()
        ->and($user->jira_access_token)->toBeNull()
        ->and($user->jira_refresh_token)->toBeNull()
        ->and($user->jira_token_expires_at)->toBeNull();
});

test('revokeAccess clears all jira credentials', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-id-123',
        'jira_account_id'        => 'account-abc',
        'jira_access_token'      => 'some-token',
        'jira_refresh_token'     => 'some-refresh',
        'jira_token_expires_at'  => now()->addHour(),
    ]);

    $service = new JiraCloudService();
    $service->revokeAccess($user);

    $user->refresh();
    expect($user->jira_cloud_id)->toBeNull()
        ->and($user->jira_account_id)->toBeNull()
        ->and($user->jira_access_token)->toBeNull()
        ->and($user->jira_refresh_token)->toBeNull()
        ->and($user->jira_token_expires_at)->toBeNull();
});

test('hasJiraConnection returns true when cloud ID is set', function () {
    $user = User::factory()->create([
        'jira_cloud_id' => 'cloud-id-123',
    ]);

    expect($user->hasJiraConnection())->toBeTrue();
});

test('hasJiraConnection returns false when cloud ID is null', function () {
    $user = User::factory()->create([
        'jira_cloud_id' => null,
    ]);

    expect($user->hasJiraConnection())->toBeFalse();
});

test('ensureValidToken refreshes expired token before API call', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-id-123',
        'jira_account_id'        => 'account-abc',
        'jira_access_token'      => 'expired-token',
        'jira_refresh_token'     => 'valid-refresh',
        'jira_token_expires_at'  => now()->subMinutes(1),
    ]);

    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'access_token'  => 'new-token',
            'refresh_token' => 'new-refresh',
            'expires_in'    => 3600,
        ], 200),
        'api.atlassian.com/ex/jira/cloud-id-123/rest/api/3/search*' => Http::response([
            'issues' => [],
            'total'  => 0,
        ], 200),
    ]);

    $service = new JiraCloudService();
    $result  = $service->searchIssues($user, 'assignee = currentUser()');

    $user->refresh();
    expect($user->jira_access_token)->toBe('new-token');
    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});
