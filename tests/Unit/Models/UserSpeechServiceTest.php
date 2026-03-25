<?php

declare(strict_types=1);

use App\Enums\SpeechServiceMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User speech service fields', function (): void {
    it('includes speech service fields in fillable', function (): void {
        $user = new User();

        expect($user->getFillable())
            ->toContain('speech_service_mode')
            ->toContain('speech_service_url')
            ->toContain('speech_service_token');
    });

    it('hides speech_service_token from serialization', function (): void {
        $user = new User();

        expect($user->getHidden())->toContain('speech_service_token');
    });

    it('casts speech_service_mode to SpeechServiceMode enum', function (): void {
        $user = User::factory()->create([
            'speech_service_mode' => 'local',
        ]);

        expect($user->speech_service_mode)->toBe(SpeechServiceMode::Local);
    });

    it('casts speech_service_token as encrypted', function (): void {
        $user = User::factory()->create([
            'speech_service_token' => 'my-secret-token',
        ]);

        $user->refresh();

        expect($user->speech_service_token)->toBe('my-secret-token');

        $rawToken = $user->getRawOriginal('speech_service_token');
        expect($rawToken)->not->toBe('my-secret-token');
    });

    it('defaults speech_service_mode to null', function (): void {
        $user = User::factory()->create();

        expect($user->speech_service_mode)->toBeNull();
    });

    it('allows setting speech service url', function (): void {
        $user = User::factory()->create([
            'speech_service_url' => 'http://localhost:8090',
        ]);

        expect($user->speech_service_url)->toBe('http://localhost:8090');
    });
});

describe('User::isLocalSpeechMode()', function (): void {
    it('returns false when custom_url_enabled config is false', function (): void {
        config(['meetings.custom_url_enabled' => false]);

        $user = User::factory()->create([
            'speech_service_mode' => 'local',
        ]);

        expect($user->isLocalSpeechMode())->toBeFalse();
    });

    it('returns false when mode is server', function (): void {
        config(['meetings.custom_url_enabled' => true]);

        $user = User::factory()->create([
            'speech_service_mode' => 'server',
        ]);

        expect($user->isLocalSpeechMode())->toBeFalse();
    });

    it('returns true when custom_url_enabled is true and mode is local', function (): void {
        config(['meetings.custom_url_enabled' => true]);

        $user = User::factory()->create([
            'speech_service_mode' => 'local',
        ]);

        expect($user->isLocalSpeechMode())->toBeTrue();
    });

    it('returns false when mode is null', function (): void {
        config(['meetings.custom_url_enabled' => true]);

        $user = User::factory()->create();

        expect($user->isLocalSpeechMode())->toBeFalse();
    });

    it('returns true when custom_url_enabled is true and mode is local even if server transcription is disabled', function (): void {
        config([
            'meetings.custom_url_enabled' => true,
            'meetings.transcription.enabled' => false,
        ]);

        $user = User::factory()->create([
            'speech_service_mode' => 'local',
        ]);

        expect($user->isLocalSpeechMode())->toBeTrue();
    });
});
