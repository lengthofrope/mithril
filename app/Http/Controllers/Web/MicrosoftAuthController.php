<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\MicrosoftGraphService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles the Microsoft Office 365 OAuth2 flow for connecting and disconnecting
 * a user's Microsoft account.
 */
class MicrosoftAuthController extends Controller
{
    /**
     * @param MicrosoftGraphService $microsoftGraphService
     */
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
    ) {}

    /**
     * Generate a CSRF state token, store it in the session, and redirect the user
     * to Microsoft's OAuth2 authorization screen.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put('microsoft_oauth_state', $state);

        $url = $this->microsoftGraphService->getAuthorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * Handle the OAuth2 callback from Microsoft.
     *
     * Validates the CSRF state, exchanges the authorization code for tokens,
     * and persists the Microsoft credentials on the authenticated user.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->get('microsoft_oauth_state');
        $returnedState = $request->query('state');

        if ($request->query('error') || $returnedState !== $storedState) {
            return redirect()->route('settings.index')
                ->with('error', 'Microsoft authorization failed. Please try again.');
        }

        try {
            $code = (string) $request->query('code');
            $tokenResponse = $this->microsoftGraphService->exchangeCodeForTokens($code);

            $user = $request->user();

            $user->microsoft_id               = $tokenResponse->microsoftId;
            $user->microsoft_email            = $tokenResponse->email;
            $user->microsoft_access_token     = $tokenResponse->accessToken;
            $user->microsoft_refresh_token    = $tokenResponse->refreshToken;
            $user->microsoft_token_expires_at = $tokenResponse->expiresAt;
            $user->save();

            $request->session()->forget('microsoft_oauth_state');

            return redirect()->route('settings.index')
                ->with('status', 'Your Microsoft account has been connected successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('settings.index')
                ->with('error', 'Failed to connect your Microsoft account. Please try again.');
        }
    }

    /**
     * Revoke the authenticated user's Microsoft access and delete all associated
     * calendar events.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->microsoftGraphService->revokeAccess($user);

        CalendarEvent::where('user_id', $user->id)->delete();

        return redirect()->route('settings.index')
            ->with('status', 'Your Microsoft account has been disconnected.');
    }
}
