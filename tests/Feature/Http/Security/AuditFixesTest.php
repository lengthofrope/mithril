<?php

declare(strict_types=1);

use App\Enums\ApiAbility;
use App\Models\User;

describe('H1: Fail-closed ability enforcement', function (): void {
    it('denies token access to session-only routes that were moved from dual-guard', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->getJson('/api/v1/speech-service/health', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnauthorized();
    });

    it('denies token access to system-notifications dismiss', function (): void {
        $user = User::factory()->create();
        $notification = \App\Models\SystemNotification::factory()->create();
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->patchJson("/api/v1/system-notifications/{$notification->id}/dismiss", [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnauthorized();
    });

    it('denies token access to attachment destroy', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->deleteJson('/api/v1/attachments/1', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnauthorized();
    });

    it('returns 403 for token requests on unmapped routes within the dual-guard group', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'New task',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden();
    });

    it('allows session user to access routes moved to session-only group', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/speech-service/health');

        expect($response->status())->not->toBe(401);
        expect($response->status())->not->toBe(403);
    });

    it('maps counters route with implicit read action', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::CountersRead->value])->plainTextToken;

        $response = $this->getJson('/api/v1/counters', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
    });

    it('denies counters route when token lacks counters:read', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

        $response = $this->getJson('/api/v1/counters', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden();
    });

    it('allows token with activities:write to store activity via named route', function (): void {
        $user = User::factory()->create();
        $task = \App\Models\Task::factory()->for($user)->create();
        $token = $user->createToken('test', [
            ApiAbility::ActivitiesWrite->value,
        ])->plainTextToken;

        $response = $this->postJson("/api/v1/tasks/{$task->id}/activities", [
            'body' => 'Test activity',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        expect($response->status())->not->toBe(403);
    });

    it('allows token with search:read to access search', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::SearchRead->value])->plainTextToken;

        $response = $this->getJson('/api/v1/search?q=test', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        expect($response->status())->not->toBe(403);
    });

    it('allows token with export:read to access export', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::ExportRead->value])->plainTextToken;

        $response = $this->getJson('/api/v1/export', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        expect($response->status())->not->toBe(403);
    });

    it('allows token with export:write to access import', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::ExportWrite->value])->plainTextToken;

        $response = $this->postJson('/api/v1/import', [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('data.json', 1),
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('M1: CSRF bypass tightening', function (): void {
    it('delegates to CSRF middleware when bearer token present with session user', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = \Illuminate\Http\Request::create('/api/v1/tasks', 'POST');
        $request->headers->set('Authorization', 'Bearer fake-token');
        $request->setLaravelSession(app('session.store'));

        $csrfMiddleware = Mockery::mock(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $csrfMiddleware->shouldReceive('handle')->once()->andReturnUsing(
            fn ($req, $next) => new \Illuminate\Http\Response('csrf-enforced', 419),
        );

        $middleware = new \App\Http\Middleware\ValidateCsrfTokenUnlessBearerAuth($csrfMiddleware);
        $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('ok'));

        expect($response->getStatusCode())->toBe(419);
    });

    it('skips CSRF middleware when bearer token present without session user', function (): void {
        \Illuminate\Support\Facades\Auth::guard('web')->logout();

        $request = \Illuminate\Http\Request::create('/api/v1/tasks', 'POST');
        $request->headers->set('Authorization', 'Bearer some-token');
        $request->setLaravelSession(app('session.store'));

        $csrfMiddleware = Mockery::mock(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $csrfMiddleware->shouldNotReceive('handle');

        $middleware = new \App\Http\Middleware\ValidateCsrfTokenUnlessBearerAuth($csrfMiddleware);
        $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('delegates to CSRF middleware when no bearer token present', function (): void {
        $request = \Illuminate\Http\Request::create('/api/v1/tasks', 'POST');
        $request->setLaravelSession(app('session.store'));

        $csrfMiddleware = Mockery::mock(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $csrfMiddleware->shouldReceive('handle')->once()->andReturnUsing(
            fn ($req, $next) => new \Illuminate\Http\Response('csrf-enforced', 419),
        );

        $middleware = new \App\Http\Middleware\ValidateCsrfTokenUnlessBearerAuth($csrfMiddleware);
        $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('ok'));

        expect($response->getStatusCode())->toBe(419);
    });

    it('bypasses CSRF for valid Bearer token without session in integration test', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [
            ApiAbility::TasksRead->value,
            ApiAbility::TasksWrite->value,
        ])->plainTextToken;

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Token task',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201);
    });
});

describe('M2: Per-user token creation limit', function (): void {
    it('rejects token creation when user already has 25 tokens', function (): void {
        $user = User::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $user->createToken("token-{$i}", ['tasks:read']);
        }

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'One too many',
            'scope' => 'read-only',
        ]);

        $response->assertUnprocessable()
            ->assertJson(['success' => false])
            ->assertJsonFragment(['message' => 'Maximum number of tokens (25) reached. Please revoke an existing token first.']);
    });

    it('allows token creation when user has fewer than 25 tokens', function (): void {
        $user = User::factory()->create();

        for ($i = 0; $i < 24; $i++) {
            $user->createToken("token-{$i}", ['tasks:read']);
        }

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Within limit',
            'scope' => 'read-only',
        ]);

        $response->assertOk();
    });
});

describe('L1: Token expiration configured', function (): void {
    it('has a non-null expiration value in sanctum config', function (): void {
        $expiration = config('sanctum.expiration');

        expect($expiration)->not->toBeNull();
        expect($expiration)->toBeGreaterThan(0);
    });
});

describe('I1: Token prefix configured', function (): void {
    it('has mtl_ prefix in sanctum config', function (): void {
        $prefix = config('sanctum.token_prefix');

        expect($prefix)->toBe('mtl_');
    });
});

describe('I2: Wildcard ability blocked', function (): void {
    it('rejects token creation with wildcard ability', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Wildcard key',
            'abilities' => ['*'],
        ]);

        $response->assertUnprocessable();
    });

    it('rejects token creation with wildcard mixed into valid abilities', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Sneaky key',
            'abilities' => ['tasks:read', '*'],
        ]);

        $response->assertUnprocessable();
    });
});

describe('I3: Generic 403 message', function (): void {
    it('does not reveal the required ability in 403 response', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Forbidden',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden();

        $message = $response->json('message');
        expect($message)->toBe('Insufficient permissions.');
        expect($message)->not->toContain('tasks:write');
    });
});
