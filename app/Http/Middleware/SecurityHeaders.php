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
        $fontSrc = "'self'";
        $imgSrc = "'self' data:";
        $styleSrc = "'self' 'unsafe-inline'";

        if (Vite::isRunningHot()) {
            $viteOrigins = 'http://127.0.0.1:5173 http://localhost:5173';
            $viteWsOrigins = 'ws://127.0.0.1:5173 ws://localhost:5173';
            $connectSrc .= " {$viteOrigins} {$viteWsOrigins}";
            $fontSrc .= " {$viteOrigins}";
            $imgSrc .= " {$viteOrigins}";
            $styleSrc .= " {$viteOrigins}";
        }

        if (config('meetings.custom_url_enabled', false)) {
            $connectSrc .= ' http://localhost:* http://127.0.0.1:*';
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; style-src {$styleSrc}; img-src {$imgSrc}; font-src {$fontSrc}; connect-src {$connectSrc}; media-src 'self' blob:; worker-src 'self' blob:; frame-src https://www.youtube.com; frame-ancestors 'none'",
        );

        return $response;
    }
}
