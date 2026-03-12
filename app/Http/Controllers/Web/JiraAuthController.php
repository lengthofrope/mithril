<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JiraIssue;
use App\Services\JiraCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles the Atlassian OAuth 2.0 (3LO) flow for connecting and disconnecting
 * a user's Jira Cloud account.
 */
class JiraAuthController extends Controller
{
    /**
     * @param JiraCloudService $jiraCloudService
     */
    public function __construct(
        private readonly JiraCloudService $jiraCloudService,
    ) {}

    /**
     * Generate a CSRF state token, store it in the session, and redirect the user
     * to Atlassian's OAuth 2.0 authorization screen.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put('jira_oauth_state', $state);

        $url = $this->jiraCloudService->getAuthorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * Handle the OAuth 2.0 callback from Atlassian.
     *
     * Validates the CSRF state, exchanges the authorization code for tokens,
     * and persists the Jira credentials on the authenticated user.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        $storedState   = $request->session()->get('jira_oauth_state');
        $returnedState = $request->query('state');

        if ($request->query('error') || $returnedState !== $storedState) {
            return redirect()->route('settings.index')
                ->with('error', 'Jira authorization failed. Please try again.');
        }

        try {
            $code          = (string) $request->query('code');
            $tokenResponse = $this->jiraCloudService->exchangeCodeForTokens($code);

            $user = $request->user();

            $user->jira_cloud_id          = $tokenResponse->cloudId;
            $user->jira_account_id        = $tokenResponse->accountId;
            $user->jira_access_token      = $tokenResponse->accessToken;
            $user->jira_refresh_token     = $tokenResponse->refreshToken;
            $user->jira_token_expires_at  = $tokenResponse->expiresAt;
            $user->save();

            $request->session()->forget('jira_oauth_state');

            return redirect()->route('settings.index')
                ->with('status', 'Your Jira account has been connected successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('settings.index')
                ->with('error', 'Failed to connect your Jira account. Please try again.');
        }
    }

    /**
     * Revoke the authenticated user's Jira access and delete all associated
     * cached issues and links.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->jiraCloudService->revokeAccess($user);

        return redirect()->route('settings.index')
            ->with('status', 'Your Jira account has been disconnected.');
    }
}
