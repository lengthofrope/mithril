<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('SpeechServiceHealthController', function (): void {
    describe('system (GET /api/v1/speech-service/health)', function (): void {
        it('proxies health check to system speech service URL', function (): void {
            Http::fake([
                'localhost:8090/health' => Http::response([
                    'ready' => true,
                    'device' => 'cuda',
                    'models' => ['whisper' => 'large-v3'],
                ]),
            ]);

            config([
                'meetings.transcription.enabled' => true,
                'meetings.transcription.base_url' => 'http://localhost:8090',
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/v1/speech-service/health');

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'ready' => true,
                        'device' => 'cuda',
                    ],
                ]);
        });

        it('returns error when system speech service is unreachable', function (): void {
            Http::fake([
                'localhost:8090/health' => Http::response(null, 503),
            ]);

            config([
                'meetings.transcription.enabled' => true,
                'meetings.transcription.base_url' => 'http://localhost:8090',
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/v1/speech-service/health');

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'ready' => false,
                    ],
                ]);
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $response = $this->getJson('/api/v1/speech-service/health');

            $response->assertUnauthorized();
        });
    });
});
