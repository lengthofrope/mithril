<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to all responses.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request by appending security headers to the response.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $connectSrc = "'self'";

        if (config('meetings.custom_url_enabled', false)) {
            $connectSrc .= ' http://localhost:* http://127.0.0.1:*';
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src {$connectSrc}; media-src 'self' blob:; worker-src 'self' blob:; frame-src https://www.youtube.com; frame-ancestors 'none'",
        );

        return $response;
    }
}
