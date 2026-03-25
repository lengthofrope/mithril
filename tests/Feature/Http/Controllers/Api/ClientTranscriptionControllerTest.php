<?php

declare(strict_types=1);

use App\Enums\DiarizationStatus;
use App\Enums\SpeechServiceMode;
use App\Enums\TranscriptionStatus;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ClientTranscriptionController', function (): void {
    describe('storeResult (POST /api/v1/meetings/{meeting}/transcription/client-result)', function (): void {
        it('creates a transcription with provided content', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Hello, this is a test transcription.',
                    'language' => 'en',
                ]
            );

            $response->assertOk()
                ->assertJson(['success' => true]);

            $transcription = MeetingTranscription::where('meeting_id', $meeting->id)->first();
            expect($transcription)->not->toBeNull()
                ->and($transcription->content)->toBe('Hello, this is a test transcription.')
                ->and($transcription->language)->toBe('en')
                ->and($transcription->provider)->toBe('unified')
                ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
        });

        it('sets diarization_status to completed when diarized_content is provided', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $diarizedContent = json_encode([
                'segments' => [
                    ['speaker' => 'Speaker 1', 'start' => 0.0, 'end' => 5.0, 'text' => 'Hello'],
                ],
                'speakers' => ['Speaker 1'],
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Hello',
                    'diarized_content' => $diarizedContent,
                    'language' => 'en',
                ]
            );

            $response->assertOk();

            $transcription = MeetingTranscription::where('meeting_id', $meeting->id)->first();
            expect($transcription->diarized_content)->toBe($diarizedContent)
                ->and($transcription->diarization_status)->toBe(DiarizationStatus::Completed);
        });

        it('updates existing transcription instead of creating a new one', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            MeetingTranscription::forceCreate([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'content' => 'Old content',
                'language' => 'nl',
                'provider' => 'unified',
                'status' => TranscriptionStatus::Pending,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'New content from local service',
                    'language' => 'en',
                ]
            );

            expect(MeetingTranscription::where('meeting_id', $meeting->id)->count())->toBe(1);

            $transcription = MeetingTranscription::where('meeting_id', $meeting->id)->first();
            expect($transcription->content)->toBe('New content from local service')
                ->and($transcription->language)->toBe('en')
                ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
        });

        it('returns 403 when user is not in local mode', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Server,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Test',
                    'language' => 'en',
                ]
            );

            $response->assertForbidden();
        });

        it('returns 403 when custom_url_enabled is false', function (): void {
            config(['meetings.custom_url_enabled' => false]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Test',
                    'language' => 'en',
                ]
            );

            $response->assertForbidden();
        });

        it('validates content is required and non-empty', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => '',
                    'language' => 'en',
                ]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['content']);
        });

        it('validates language is required', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Test content',
                ]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['language']);
        });

        it('returns 404 for meeting belonging to another user', function (): void {
            config(['meetings.custom_url_enabled' => true]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Test',
                    'language' => 'en',
                ]
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $response = $this->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/client-result",
                [
                    'content' => 'Test',
                    'language' => 'en',
                ]
            );

            $response->assertUnauthorized();
        });
    });
});
