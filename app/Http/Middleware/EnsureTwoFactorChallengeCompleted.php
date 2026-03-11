<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects authenticated users with two-factor enabled to the challenge page
 * unless they have already completed the two-factor challenge for the current session.
 */
class EnsureTwoFactorChallengeCompleted
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
        $user = $request->user();

        if ($user
            && $user->hasTwoFactorEnabled()
            && !$request->session()->get('two_factor_authenticated')
            && !Auth::viaRemember()
            && !$request->is('two-factor-challenge', 'logout')
        ) {
            return redirect()->route('two-factor.challenge');
        }

        if ($user && Auth::viaRemember() && $user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor_authenticated', true);
        }

        return $next($request);
    }
}
