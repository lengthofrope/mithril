<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps Laravel's CSRF validation to skip it for Bearer token requests.
 *
 * Session-authenticated API requests still require CSRF protection.
 * Token-authenticated requests (Authorization: Bearer ...) bypass CSRF
 * only when no active session user exists. This prevents XSS from
 * exploiting a fake Bearer header to skip CSRF on session-authed requests.
 */
class ValidateCsrfTokenUnlessBearerAuth
{
    /**
     * Create a new middleware instance.
     *
     * @param ValidateCsrfToken $csrfMiddleware
     */
    public function __construct(
        private readonly ValidateCsrfToken $csrfMiddleware,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Only bypasses CSRF when a Bearer token is present AND no session user
     * is authenticated. If the user has a session, CSRF is always enforced.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null && !Auth::guard('web')->check()) {
            return $next($request);
        }

        return $this->csrfMiddleware->handle($request, $next);
    }
}
