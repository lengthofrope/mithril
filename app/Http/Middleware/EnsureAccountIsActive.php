<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks users whose account has been disabled.
 *
 * For session-based requests: logs out the user and redirects to login.
 * For token-based requests: returns a 403 JSON response without session operations.
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->is_active || $request->is('logout')) {
            return $next($request);
        }

        if ($this->isTokenAuthenticated($request)) {
            return response()->json(['message' => 'Your account has been disabled.'], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Your account has been disabled.'], 403);
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'Your account has been disabled.']);
    }

    /**
     * Determine whether the request is authenticated via a Sanctum personal access token.
     *
     * @param Request $request
     * @return bool
     */
    private function isTokenAuthenticated(Request $request): bool
    {
        return $request->user()?->currentAccessToken() instanceof PersonalAccessToken;
    }
}
