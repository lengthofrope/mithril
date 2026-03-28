<?php

declare(strict_types=1);

use App\Enums\ApiAbility;
use App\Enums\ApiScope;
use App\Models\User;

describe('CheckTokenAbility middleware', function (): void {
    describe('session-authenticated requests bypass ability checks', function (): void {
        it('allows session user to access any API route without abilities', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/v1/tasks');

            $response->assertOk();
        });

        it('allows session user to create resources without abilities', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/tasks', [
                'title' => 'Session task',
            ]);

            $response->assertStatus(201);
        });

        it('allows session user to delete resources without abilities', function (): void {
            $user = User::factory()->create();
            $task = \App\Models\Task::factory()->for($user)->create();

            $response = $this->actingAs($user)->deleteJson("/api/v1/tasks/{$task->id}");

            $response->assertOk();
        });
    });

    describe('token with specific abilities', function (): void {
        it('allows token with tasks:read to GET tasks index', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk();
        });

        it('allows token with tasks:read to GET tasks index twice', function (): void {
            $user = User::factory()->create();
            \App\Models\Task::factory()->for($user)->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->getJson('/api/v1/tasks', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('rejects token with tasks:read on POST tasks', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'New task',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'data' => null,
                ])
                ->assertJsonFragment(['message' => 'Insufficient permissions.']);
        });

        it('rejects token with tasks:read on PATCH tasks', function (): void {
            $user = User::factory()->create();
            $task = \App\Models\Task::factory()->for($user)->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->patchJson("/api/v1/tasks/{$task->id}", [
                'title' => 'Updated',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'data' => null,
                ]);
        });

        it('rejects token with tasks:read on DELETE tasks', function (): void {
            $user = User::factory()->create();
            $task = \App\Models\Task::factory()->for($user)->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->deleteJson("/api/v1/tasks/{$task->id}", [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'data' => null,
                ])
                ->assertJsonFragment(['message' => 'Insufficient permissions.']);
        });

        it('allows token with tasks:write to POST tasks', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [
                ApiAbility::TasksRead->value,
                ApiAbility::TasksWrite->value,
            ])->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'New task via token',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(201);
        });

        it('allows token with tasks:delete to DELETE tasks', function (): void {
            $user = User::factory()->create();
            $task = \App\Models\Task::factory()->for($user)->create();
            $token = $user->createToken('test', [
                ApiAbility::TasksRead->value,
                ApiAbility::TasksDelete->value,
            ])->plainTextToken;

            $response = $this->deleteJson("/api/v1/tasks/{$task->id}", [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk();
        });
    });

    describe('scope-based token access', function (): void {
        it('allows read-only token to read all resources', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', ApiScope::ReadOnly->abilityValues())->plainTextToken;

            $readRoutes = [
                '/api/v1/tasks',
                '/api/v1/teams',
                '/api/v1/team-members',
                '/api/v1/notes',
                '/api/v1/follow-ups',
                '/api/v1/meetings',
                '/api/v1/agreements',
            ];

            foreach ($readRoutes as $route) {
                $response = $this->getJson($route, [
                    'Authorization' => 'Bearer ' . $token,
                ]);

                $response->assertOk();
            }
        });

        it('rejects read-only token on write operations', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', ApiScope::ReadOnly->abilityValues())->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'Should be rejected',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden();
        });

        it('rejects read-only token on delete operations', function (): void {
            $user = User::factory()->create();
            $task = \App\Models\Task::factory()->for($user)->create();
            $token = $user->createToken('test', ApiScope::ReadOnly->abilityValues())->plainTextToken;

            $response = $this->deleteJson("/api/v1/tasks/{$task->id}", [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden();
        });

        it('allows full-access token to perform all operations', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', ApiScope::FullAccess->abilityValues())->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'Full access task',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(201);

            $taskId = $response->json('data.id');

            $response = $this->patchJson("/api/v1/tasks/{$taskId}", [
                'title' => 'Updated full access task',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk();

            $response = $this->deleteJson("/api/v1/tasks/{$taskId}", [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertOk();
        });
    });

    describe('sub-resource routes map to parent resource', function (): void {
        it('allows meetings:read token to view meeting transcription', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [ApiAbility::MeetingsRead->value])->plainTextToken;

            $meeting = \App\Models\Meeting::factory()->for($user)->create();

            $response = $this->getJson("/api/v1/meetings/{$meeting->id}/transcription", [
                'Authorization' => 'Bearer ' . $token,
            ]);

            expect($response->status())->not->toBe(403);
        });

        it('rejects meetings:read token on meeting recording store', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [ApiAbility::MeetingsRead->value])->plainTextToken;

            $meeting = \App\Models\Meeting::factory()->for($user)->create();

            $response = $this->postJson("/api/v1/meetings/{$meeting->id}/recordings", [], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden();
        });
    });

    describe('403 response format', function (): void {
        it('returns ApiResponse format on 403', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', [ApiAbility::TasksRead->value])->plainTextToken;

            $response = $this->postJson('/api/v1/tasks', [
                'title' => 'Forbidden',
            ], [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertForbidden()
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message',
                ])
                ->assertJson([
                    'success' => false,
                    'data' => null,
                ]);

            expect($response->json('message'))->toBeString()->not->toBeEmpty();
        });
    });

    describe('routes moved to session-only group', function (): void {
        it('rejects token auth on speech-service health endpoint', function (): void {
            $user = User::factory()->create();
            $token = $user->createToken('test', ['*'])->plainTextToken;

            $response = $this->getJson('/api/v1/speech-service/health', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertUnauthorized();
        });
    });
});
