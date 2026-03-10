<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TokenResponse;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Handles all communication with the Microsoft Identity Platform and Microsoft Graph API,
 * including OAuth2 token lifecycle and Graph data retrieval.
 */
class MicrosoftGraphService
{
    /**
     * Build the OAuth2 authorization URL to redirect the user to Microsoft's consent screen.
     *
     * @param string $state CSRF state token to embed in the authorization URL.
     * @return string The fully constructed authorization URL.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $baseUrl = config('microsoft.authority') . config('microsoft.tenant_id') . '/oauth2/v2.0/authorize';

        $params = http_build_query([
            'client_id'     => config('microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri'  => config('microsoft.redirect_uri'),
            'scope'         => $this->scopeString(),
            'state'         => $state,
            'response_mode' => 'query',
        ]);

        return $baseUrl . '?' . $params;
    }

    /**
     * Exchange an authorization code for access and refresh tokens.
     *
     * @param string $code The authorization code received from Microsoft's redirect.
     * @return TokenResponse Populated DTO containing tokens, expiry, and user identity.
     * @throws RuntimeException When the token exchange request fails.
     */
    public function exchangeCodeForTokens(string $code): TokenResponse
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_id'     => config('microsoft.client_id'),
            'client_secret' => config('microsoft.client_secret'),
            'code'          => $code,
            'redirect_uri'  => config('microsoft.redirect_uri'),
            'grant_type'    => 'authorization_code',
            'scope'         => $this->scopeString(),
        ]);

        $this->assertSuccessfulTokenResponse($response);

        $body        = $response->json();
        $accessToken = $body['access_token'];
        $expiresAt   = now()->addSeconds((int) $body['expires_in']);

        $profile = $this->getMyProfile($accessToken);

        return new TokenResponse(
            accessToken:  $accessToken,
            refreshToken: $body['refresh_token'],
            expiresAt:    $expiresAt,
            microsoftId:  $profile['id'],
            email:        $profile['mail'] ?? $profile['userPrincipalName'],
        );
    }

    /**
     * Refresh a user's access token using their stored refresh token.
     *
     * Clears all Microsoft credentials from the user record and throws when the
     * refresh grant is rejected (e.g. consent was revoked).
     *
     * @param User $user The user whose token should be refreshed.
     * @return void
     * @throws RuntimeException When the refresh token is invalid or consent was revoked.
     */
    public function refreshAccessToken(User $user): void
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_id'     => config('microsoft.client_id'),
            'client_secret' => config('microsoft.client_secret'),
            'refresh_token' => $user->microsoft_refresh_token,
            'grant_type'    => 'refresh_token',
            'scope'         => $this->scopeString(),
        ]);

        if ($response->failed()) {
            $this->clearMicrosoftCredentials($user);

            throw new RuntimeException(
                'Microsoft token refresh failed — consent may have been revoked. '
                . 'Status: ' . $response->status() . '. '
                . 'Detail: ' . ($response->json('error_description') ?? $response->body())
            );
        }

        $body = $response->json();

        $user->microsoft_access_token    = $body['access_token'];
        $user->microsoft_refresh_token   = $body['refresh_token'];
        $user->microsoft_token_expires_at = now()->addSeconds((int) $body['expires_in']);
        $user->save();
    }

    /**
     * Fetch the authenticated user's profile from Microsoft Graph.
     *
     * @param string $accessToken A valid Bearer access token.
     * @return array{id: string, mail: string|null, userPrincipalName: string} The raw profile fields.
     * @throws RuntimeException When the Graph request fails.
     */
    public function getMyProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get(config('microsoft.graph_url') . 'me', [
                '$select' => 'id,mail,userPrincipalName',
            ]);

        $this->assertSuccessfulGraphResponse($response);

        return $response->json();
    }

    /**
     * Retrieve calendar events for the authenticated user within a date range.
     *
     * @param User             $user The user whose calendar should be queried.
     * @param CarbonInterface  $from Range start (inclusive).
     * @param CarbonInterface  $to   Range end (inclusive).
     * @return Collection<int, array<string, mixed>> Normalised calendar event records.
     * @throws RuntimeException When the Graph request fails.
     */
    public function getMyCalendarEvents(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $this->ensureValidToken($user);

        $response = Http::withToken($user->microsoft_access_token)
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->get(config('microsoft.graph_url') . 'me/calendarView', [
                'startDateTime' => $from->toIso8601String(),
                'endDateTime'   => $to->toIso8601String(),
                '$select'       => 'id,subject,start,end,isAllDay,location,showAs,isOnlineMeeting,onlineMeeting,organizer',
                '$orderby'      => 'start/dateTime',
                '$top'          => 100,
            ]);

        $this->assertSuccessfulGraphResponse($response);

        return collect($response->json('value', []))
            ->map(fn (array $event): array => $this->normaliseCalendarEvent($event));
    }

    /**
     * Retrieve schedule availability for a set of email addresses within a date range.
     *
     * @param User            $user   The requesting user.
     * @param array<int, string> $emails Email addresses to check availability for.
     * @param CarbonInterface $from   Range start.
     * @param CarbonInterface $to     Range end.
     * @return Collection<int, array<string, mixed>> Availability records keyed by email.
     * @throws RuntimeException When the Graph request fails.
     */
    public function getScheduleAvailability(
        User $user,
        array $emails,
        CarbonInterface $from,
        CarbonInterface $to
    ): Collection {
        $this->ensureValidToken($user);

        $response = Http::withToken($user->microsoft_access_token)
            ->post(config('microsoft.graph_url') . 'me/calendar/getSchedule', [
                'schedules'                => $emails,
                'startTime'               => [
                    'dateTime' => $from->toIso8601String(),
                    'timeZone' => 'UTC',
                ],
                'endTime'                 => [
                    'dateTime' => $to->toIso8601String(),
                    'timeZone' => 'UTC',
                ],
                'availabilityViewInterval' => 60,
            ]);

        $this->assertSuccessfulGraphResponse($response);

        return collect($response->json('value', []))
            ->map(fn (array $schedule): array => [
                'email'        => $schedule['scheduleId'],
                'availability' => $schedule['availabilityView'] ?? $schedule['scheduleItems'] ?? [],
            ]);
    }

    /**
     * Revoke all stored Microsoft credentials from the user record.
     *
     * @param User $user The user whose Microsoft access should be cleared.
     * @return void
     */
    public function revokeAccess(User $user): void
    {
        $this->clearMicrosoftCredentials($user);
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
        if ($user->microsoft_token_expires_at === null) {
            throw new RuntimeException('User has no Microsoft token. Re-authentication required.');
        }

        $expiresAt = $user->microsoft_token_expires_at instanceof CarbonInterface
            ? $user->microsoft_token_expires_at
            : now()->parse($user->microsoft_token_expires_at);

        if ($expiresAt->isBefore(now()->addMinutes(5))) {
            $this->refreshAccessToken($user);
        }
    }

    /**
     * Build the Microsoft Identity Platform token endpoint URL.
     *
     * @return string The token endpoint URL.
     */
    private function tokenUrl(): string
    {
        return config('microsoft.authority') . config('microsoft.tenant_id') . '/oauth2/v2.0/token';
    }

    /**
     * Join the configured scopes into a space-delimited string.
     *
     * @return string The space-joined scope string.
     */
    private function scopeString(): string
    {
        return implode(' ', config('microsoft.scopes'));
    }

    /**
     * Clear all Microsoft-related columns on the user record and persist.
     *
     * @param User $user The user to clear credentials from.
     * @return void
     */
    private function clearMicrosoftCredentials(User $user): void
    {
        $user->microsoft_id               = null;
        $user->microsoft_access_token     = null;
        $user->microsoft_refresh_token    = null;
        $user->microsoft_token_expires_at = null;
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
            'Microsoft token request failed. '
            . 'Status: ' . $response->status() . '. '
            . 'Detail: ' . ($response->json('error_description') ?? $response->body())
        );
    }

    /**
     * Assert that a Graph API response is successful, throwing on failure with
     * Retry-After detail for 429 rate-limit responses.
     *
     * @param Response $response The HTTP response from the Graph API.
     * @return void
     * @throws RuntimeException When the response indicates a failure.
     */
    private function assertSuccessfulGraphResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 'unknown';

            throw new RuntimeException(
                'Microsoft Graph API rate limit exceeded (429). '
                . 'Retry-After: ' . $retryAfter . ' seconds.'
            );
        }

        $errorDetail = $response->json('error.message') ?? $response->body();

        throw new RuntimeException(
            'Microsoft Graph API request failed. '
            . 'Status: ' . $response->status() . '. '
            . 'Detail: ' . $errorDetail
        );
    }

    /**
     * Map a raw Graph API calendar event array to the application's normalised schema.
     *
     * @param array<string, mixed> $event Raw event data from the Graph calendarView response.
     * @return array<string, mixed> Normalised event record.
     */
    private function normaliseCalendarEvent(array $event): array
    {
        return [
            'microsoft_event_id'  => $event['id'],
            'subject'             => $event['subject'] ?? null,
            'start_at'            => $event['start']['dateTime'] ?? null,
            'end_at'              => $event['end']['dateTime'] ?? null,
            'is_all_day'          => $event['isAllDay'] ?? false,
            'location'            => $event['location']['displayName'] ?? null,
            'status'              => $event['showAs'] ?? null,
            'is_online_meeting'   => $event['isOnlineMeeting'] ?? false,
            'online_meeting_url'  => $event['onlineMeeting']['joinUrl'] ?? null,
            'organizer_name'      => $event['organizer']['emailAddress']['name'] ?? null,
            'organizer_email'     => $event['organizer']['emailAddress']['address'] ?? null,
        ];
    }
}
