<?php

declare(strict_types=1);

use App\Enums\SpeechServiceMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Settings — Speech Service', function (): void {
    describe('updateSpeechService (PATCH /settings/speech-service)', function (): void {
        it('saves speech service mode', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                [
                    'speech_service_mode' => 'local',
                    'speech_service_url' => 'http://localhost:8090',
                ]
            );

            $response->assertOk()
                ->assertJson(['success' => true]);

            expect($user->fresh()->speech_service_mode)->toBe(SpeechServiceMode::Local);
        });

        it('saves speech service url and token', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                [
                    'speech_service_mode' => 'local',
                    'speech_service_url' => 'http://localhost:8090',
                    'speech_service_token' => 'my-secret',
                ]
            );

            $response->assertOk();

            $user->refresh();
            expect($user->speech_service_url)->toBe('http://localhost:8090')
                ->and($user->speech_service_token)->toBe('my-secret');
        });

        it('rejects invalid mode value', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                ['speech_service_mode' => 'invalid']
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['speech_service_mode']);
        });

        it('requires url when mode is local', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                [
                    'speech_service_mode' => 'local',
                    'speech_service_url' => '',
                ]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['speech_service_url']);
        });

        it('allows empty url when mode is server', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_url' => 'http://localhost:8090',
            ]);

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                [
                    'speech_service_mode' => 'server',
                    'speech_service_url' => '',
                ]
            );

            $response->assertOk();
        });

        it('returns 403 when custom_url_enabled is false', function (): void {
            config(['meetings.custom_url_enabled' => false]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->patchJson(
                route('settings.updateSpeechService'),
                ['speech_service_mode' => 'local']
            );

            $response->assertForbidden();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $response = $this->patchJson(
                route('settings.updateSpeechService'),
                ['speech_service_mode' => 'local']
            );

            $response->assertUnauthorized();
        });
    });

    describe('settings page visibility', function (): void {
        it('shows speech service section when custom_url_enabled is true', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('settings.index'));

            $response->assertOk()
                ->assertSee('Speech Service');
        });

        it('hides speech service section when custom_url_enabled is false', function (): void {
            config(['meetings.custom_url_enabled' => false]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('settings.index'));

            $response->assertOk()
                ->assertDontSee('Speech Service');
        });
    });
});
