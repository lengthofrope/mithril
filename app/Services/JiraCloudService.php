<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\JiraTokenResponse;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Handles all communication with the Atlassian Identity Platform and Jira Cloud REST API,
 * including OAuth 2.0 (3LO) token lifecycle and issue retrieval.
 */
class JiraCloudService
{
    /**
     * Build the OAuth 2.0 authorization URL to redirect the user to Atlassian's consent screen.
     *
     * @param string $state CSRF state token to embed in the authorization URL.
     * @return string The fully constructed authorization URL.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'audience'      => 'api.atlassian.com',
            'client_id'     => config('jira.client_id'),
            'scope'         => $this->scopeString(),
            'redirect_uri'  => config('jira.redirect_uri'),
            'state'         => $state,
            'response_type' => 'code',
            'prompt'        => 'consent',
        ]);

        return config('jira.auth_url') . '?' . $params;
    }

    /**
     * Exchange an authorization code for access and refresh tokens, then fetch the cloud ID.
     *
     * @param string $code The authorization code received from Atlassian's redirect.
     * @return JiraTokenResponse Populated DTO containing tokens, expiry, cloud ID, and account ID.
     * @throws RuntimeException When the token exchange or resource fetch fails.
     */
    public function exchangeCodeForTokens(string $code): JiraTokenResponse
    {
        $response = Http::asForm()->post(config('jira.token_url'), [
            'grant_type'    => 'authorization_code',
            'client_id'     => config('jira.client_id'),
            'client_secret' => config('jira.client_secret'),
            'code'          => $code,
            'redirect_uri'  => config('jira.redirect_uri'),
        ]);

        $this->assertSuccessfulTokenResponse($response);

        $body        = $response->json();
        $accessToken = $body['access_token'];
        $expiresAt   = now()->addSeconds((int) $body['expires_in']);

        $cloudId   = $this->fetchCloudId($accessToken);
        $accountId = $this->fetchAccountId($accessToken);

        return new JiraTokenResponse(
            accessToken:  $accessToken,
            refreshToken: $body['refresh_token'],
            expiresAt:    $expiresAt,
            cloudId:      $cloudId,
            accountId:    $accountId,
        );
    }

    /**
     * Refresh a user's access token using their stored refresh token.
     *
     * @param User $user The user whose token should be refreshed.
     * @return void
     * @throws RuntimeException When the refresh token is invalid or consent was revoked.
     */
    public function refreshAccessToken(User $user): void
    {
        $response = Http::asForm()->post(config('jira.token_url'), [
            'grant_type'    => 'refresh_token',
            'client_id'     => config('jira.client_id'),
            'client_secret' => config('jira.client_secret'),
            'refresh_token' => $user->jira_refresh_token,
        ]);

        if ($response->failed()) {
            $this->clearJiraCredentials($user);

            throw new RuntimeException(
                'Jira token refresh failed — consent may have been revoked. '
                . 'Status: ' . $response->status() . '. '
                . 'Detail: ' . ($response->json('error_description') ?? $response->body())
            );
        }

        $body = $response->json();

        $user->jira_access_token     = $body['access_token'];
        $user->jira_refresh_token    = $body['refresh_token'];
        $user->jira_token_expires_at = now()->addSeconds((int) $body['expires_in']);
        $user->save();
    }

    /**
     * Execute a JQL search against the Jira Cloud REST API.
     *
     * @param User   $user The user whose Jira connection should be used.
     * @param string $jql  The JQL query string.
     * @param int    $maxResults Maximum number of results to return.
     * @return Collection<int, array<string, mixed>> The raw issue arrays from Jira.
     * @throws RuntimeException When the API request fails.
     */
    public function searchIssues(User $user, string $jql, int $maxResults = 50): Collection
    {
        $this->ensureValidToken($user);

        $response = Http::withToken($user->jira_access_token)
            ->get($this->apiUrl($user, '/rest/api/3/search'), [
                'jql'        => $jql,
                'maxResults' => $maxResults,
                'fields'     => 'summary,description,project,issuetype,status,priority,assignee,reporter,labels,updated',
            ]);

        $this->assertSuccessfulApiResponse($response);

        return collect($response->json('issues', []));
    }

    /**
     * Revoke all stored Jira credentials from the user record.
     *
     * @param User $user The user whose Jira access should be cleared.
     * @return void
     */
    public function revokeAccess(User $user): void
    {
        $this->clearJiraCredentials($user);
    }

    /**
     * Refresh the user's access token if it has expired or expires within 5 minutes.
     *
     * @param User $user The user to check and refresh.
     * @return void
     * @throws RuntimeException When the refresh fails.
     */
    private function ensureValidToken(User $user): void
    {
        if ($user->jira_token_expires_at === null) {
            throw new RuntimeException('User has no Jira token. Re-authentication required.');
        }

        $expiresAt = $user->jira_token_expires_at instanceof CarbonInterface
            ? $user->jira_token_expires_at
            : now()->parse($user->jira_token_expires_at);

        if ($expiresAt->isBefore(now()->addMinutes(5))) {
            $this->refreshAccessToken($user);
        }
    }

    /**
     * Fetch the first accessible Jira Cloud site ID for the given access token.
     *
     * @param string $accessToken A valid Bearer access token.
     * @return string The Cloud site ID.
     * @throws RuntimeException When no accessible resources are found.
     */
    private function fetchCloudId(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get(config('jira.resources_url'));

        if ($response->failed() || empty($response->json())) {
            throw new RuntimeException(
                'No accessible Jira Cloud sites found. The user may not have granted access to any site.'
            );
        }

        return $response->json('0.id');
    }

    /**
     * Fetch the authenticated user's Atlassian account ID.
     *
     * @param string $accessToken A valid Bearer access token.
     * @return string The Atlassian account ID.
     * @throws RuntimeException When the request fails.
     */
    private function fetchAccountId(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get('https://api.atlassian.com/me');

        if ($response->failed()) {
            throw new RuntimeException(
                'Failed to fetch Atlassian account info. Status: ' . $response->status()
            );
        }

        return $response->json('account_id');
    }

    /**
     * Build the Jira Cloud REST API base URL for the given user.
     *
     * @param User   $user The user whose cloud ID to use.
     * @param string $path The API path to append.
     * @return string The full API URL.
     */
    private function apiUrl(User $user, string $path): string
    {
        return config('jira.api_base_url') . $user->jira_cloud_id . $path;
    }

    /**
     * Join the configured scopes into a space-delimited string.
     *
     * @return string The space-joined scope string.
     */
    private function scopeString(): string
    {
        return implode(' ', config('jira.scopes'));
    }

    /**
     * Clear all Jira-related columns on the user record and persist.
     *
     * @param User $user The user to clear credentials from.
     * @return void
     */
    private function clearJiraCredentials(User $user): void
    {
        $user->jira_cloud_id          = null;
        $user->jira_account_id        = null;
        $user->jira_access_token      = null;
        $user->jira_refresh_token     = null;
        $user->jira_token_expires_at  = null;
        $user->save();
    }

    /**
     * Assert that a token endpoint response is successful, throwing on failure.
     *
     * @param Response $response The HTTP response from the token endpoint.
     * @return void
     * @throws RuntimeException When the response indicates a failure.
     */
    private function assertSuccessfulTokenResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(
            'Jira token request failed. '
            . 'Status: ' . $response->status() . '. '
            . 'Detail: ' . ($response->json('error_description') ?? $response->body())
        );
    }

    /**
     * Assert that a Jira API response is successful, throwing on failure with
     * Retry-After detail for 429 rate-limit responses.
     *
     * @param Response $response The HTTP response from the Jira API.
     * @return void
     * @throws RuntimeException When the response indicates a failure.
     */
    private function assertSuccessfulApiResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 'unknown';

            throw new RuntimeException(
                'Jira Cloud API rate limit exceeded (429). '
                . 'Retry-After: ' . $retryAfter . ' seconds.'
            );
        }

        $errorDetail = $response->json('errorMessages.0') ?? $response->body();

        throw new RuntimeException(
            'Jira Cloud API request failed. '
            . 'Status: ' . $response->status() . '. '
            . 'Detail: ' . $errorDetail
        );
    }
}
