<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps Laravel's CSRF validation to skip it for Bearer token requests.
 *
 * Session-authenticated API requests still require CSRF protection.
 * Token-authenticated requests (Authorization: Bearer ...) bypass CSRF
 * since they use a stateless authentication mechanism.
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
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            return $next($request);
        }

        return $this->csrfMiddleware->handle($request, $next);
    }
}
