<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out and redirects users whose account has been disabled.
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
        if ($request->user() && !$request->user()->is_active && !$request->is('logout')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account has been disabled.'], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been disabled.']);
        }

        return $next($request);
    }
}
