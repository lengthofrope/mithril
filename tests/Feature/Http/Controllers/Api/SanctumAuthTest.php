<?php

declare(strict_types=1);

use App\Models\User;

describe('Sanctum dual-guard authentication', function (): void {
    describe('Bearer token authentication', function (): void {
        it('authenticates a valid Sanctum token and returns API data', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk()
                ->assertJsonStructure(['success', 'data'])
                ->assertJson(['success' => true]);
        });

        it('returns 401 in ApiResponse format for missing token', function (): void {
            $response = $this->getJson('/api/v1/tasks');

            $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ]);
        });

        it('returns 401 in ApiResponse format for invalid token', function (): void {
            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer invalid-token-value',
            ]);

            $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ]);
        });

        it('returns 401 in ApiResponse format for revoked token', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token');
            $plainText = $token->plainTextToken;

            $token->accessToken->delete();

            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer ' . $plainText,
            ]);

            $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ]);
        });
    });

    describe('session-based authentication continues working', function (): void {
        it('allows session-authenticated requests to API routes', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/v1/tasks');

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 401 for unauthenticated session requests', function (): void {
            $response = $this->getJson('/api/v1/tasks');

            $response->assertUnauthorized();
        });
    });

    describe('CSRF handling for token requests', function (): void {
        it('does not require CSRF token for Bearer-authenticated requests', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'Test task via API token',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(201);
        });
    });

    describe('EnsureAccountIsActive middleware with tokens', function (): void {
        it('blocks disabled users authenticating via Sanctum token', function (): void {
            $user = User::factory()->disabled()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden()
                ->assertJson(['message' => 'Your account has been disabled.']);
        });

        it('blocks disabled users authenticating via session on API routes', function (): void {
            $user = User::factory()->disabled()->create();

            $response = $this->actingAs($user)->getJson('/api/v1/tasks');

            $response->assertForbidden()
                ->assertJson(['message' => 'Your account has been disabled.']);
        });
    });

    describe('session-only routes remain inaccessible via token', function (): void {
        it('rejects token auth on auto-save endpoint', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->postJson('/api/v1/auto-save', [
                'model' => 'Task',
                'id' => 1,
                'field' => 'title',
                'value' => 'updated',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertUnauthorized();
        });

        it('rejects token auth on reorder endpoint', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->postJson('/api/v1/reorder', [
                'model' => 'Task',
                'ids' => [1, 2, 3],
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertUnauthorized();
        });

        it('rejects token auth on sync endpoints', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->postJson('/api/v1/sync/calendar', [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertUnauthorized();
        });
    });
});
