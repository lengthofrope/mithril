<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Vite;

describe('SecurityHeaders middleware', function (): void {
    it('includes vite dev server origins in connect-src when vite is running', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toContain('connect-src')
            ->and($csp)->toMatch('/connect-src[^;]*http:\/\/localhost:5173/')
            ->and($csp)->toMatch('/connect-src[^;]*ws:\/\/localhost:5173/')
            ->and($csp)->toMatch('/connect-src[^;]*http:\/\/127\.0\.0\.1:5173/')
            ->and($csp)->toMatch('/connect-src[^;]*ws:\/\/127\.0\.0\.1:5173/');
    });

    it('includes vite dev server origin in font-src when vite is running', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toMatch('/font-src[^;]*http:\/\/localhost:5173/')
            ->toMatch('/font-src[^;]*http:\/\/127\.0\.0\.1:5173/');
    });

    it('includes vite dev server origin in img-src when vite is running', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toMatch('/img-src[^;]*http:\/\/localhost:5173/')
            ->toMatch('/img-src[^;]*http:\/\/127\.0\.0\.1:5173/');
    });

    it('includes vite dev server origin in style-src when vite is running', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toMatch('/style-src[^;]*http:\/\/localhost:5173/')
            ->toMatch('/style-src[^;]*http:\/\/127\.0\.0\.1:5173/');
    });

    it('produces identical CSP directives to current hardcoded values when vite is not running', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(false);
        config()->set('meetings.custom_url_enabled', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toContain("default-src 'self'")
            ->toContain("script-src 'self' 'nonce-test-nonce' 'unsafe-eval'")
            ->toContain("style-src 'self' 'unsafe-inline';")
            ->toContain("img-src 'self' data:;")
            ->toContain("font-src 'self';")
            ->toContain("connect-src 'self';")
            ->toContain("media-src 'self' blob:")
            ->toContain("worker-src 'self' blob:")
            ->toContain("frame-src https://www.youtube.com")
            ->toContain("frame-ancestors 'none'")
            ->not->toContain('localhost:5173')
            ->not->toContain('127.0.0.1:5173');
    });

    it('does not include dev-mode origins in production even with APP_ENV=local when vite is not running', function (): void {
        app()->detectEnvironment(fn () => 'local');
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(false);
        config()->set('meetings.custom_url_enabled', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->not->toContain('localhost:5173')
            ->not->toContain('127.0.0.1:5173');
    });

    it('includes both vite and custom url origins in connect-src when both conditions are true', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce');
        Vite::shouldReceive('isRunningHot')->andReturn(true);
        config()->set('meetings.custom_url_enabled', true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)
            ->toMatch('/connect-src[^;]*http:\/\/localhost:5173/')
            ->toMatch('/connect-src[^;]*ws:\/\/localhost:5173/')
            ->toMatch('/connect-src[^;]*http:\/\/127\.0\.0\.1:5173/')
            ->toMatch('/connect-src[^;]*ws:\/\/127\.0\.0\.1:5173/')
            ->toMatch('/connect-src[^;]*http:\/\/localhost:\*/')
            ->toMatch('/connect-src[^;]*http:\/\/127\.0\.0\.1:\*/');
    });

    it('includes a nonce in script-src regardless of vite state', function (): void {
        Vite::shouldReceive('useCspNonce')->andReturn('test-nonce-hot');
        Vite::shouldReceive('isRunningHot')->andReturn(true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        expect($csp)->toContain("script-src 'self' 'nonce-test-nonce-hot' 'unsafe-eval'");
    });
});
