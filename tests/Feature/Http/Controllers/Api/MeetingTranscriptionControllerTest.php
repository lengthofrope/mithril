<?php

declare(strict_types=1);

use App\Enums\DiarizationStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\DiarizeMeetingJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('MeetingTranscriptionController', function (): void {
    describe('show (GET /api/v1/meetings/{meeting}/transcription)', function (): void {
        it('returns null status and content when no transcription exists', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => null,
                        'content' => null,
                    ],
                ]);
        });

        it('returns transcription data when a completed transcription exists', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'content' => 'Hello, this is the transcription.',
                'language' => 'en',
                'provider' => 'whisper',
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => 'completed',
                        'content' => 'Hello, this is the transcription.',
                        'language' => 'en',
                        'provider' => 'whisper',
                    ],
                ]);
        });

        it('returns error_message for a failed transcription', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->failed()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => 'failed',
                        'error_message' => 'Transcription service unavailable.',
                    ],
                ]);
        });

        it('returns the full transcription response structure', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'status',
                        'content',
                        'diarized_content',
                        'diarization_status',
                        'diarization_error',
                        'language',
                        'provider',
                        'error_message',
                        'updated_at',
                        'processing_started_at',
                        'audio_duration_seconds',
                        'estimated_duration_seconds',
                    ],
                ]);
        });

        it('returns processing_started_at for a processing transcription', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
                'processing_started_at' => now(),
                'audio_duration_seconds' => 600,
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJson([
                    'data' => [
                        'status' => 'processing',
                        'audio_duration_seconds' => 600,
                    ],
                ]);

            expect($response->json('data.processing_started_at'))->not->toBeNull();
        });

        it('returns estimated_duration_seconds based on historical ratio', function (): void {
            $user = User::factory()->create();

            $completedMeeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $completedMeeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'audio_duration_seconds' => 600,
                'processing_duration_seconds' => 60,
            ]);

            $currentMeeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $currentMeeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
                'processing_started_at' => now(),
                'audio_duration_seconds' => 1200,
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$currentMeeting->id}/transcription"
            );

            $response->assertOk();
            expect($response->json('data.estimated_duration_seconds'))->toBe(120);
        });

        it('returns null estimated_duration_seconds when no historical data exists', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
                'processing_started_at' => now(),
                'audio_duration_seconds' => 600,
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk();
            expect($response->json('data.estimated_duration_seconds'))->toBeNull();
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $response = $this->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertUnauthorized();
        });
    });

    describe('retry (POST /api/v1/meetings/{meeting}/transcription/retry)', function (): void {
        it('dispatches a TranscribeMeetingJob when a recording exists', function (): void {
            Queue::fake();
            Config::set('meetings.diarization.enabled', false);

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            Queue::assertPushed(TranscribeMeetingJob::class);
        });

        it('chains diarization after transcription when diarization is enabled', function (): void {
            Bus::fake();
            Config::set('meetings.diarization.enabled', true);

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            Bus::assertChained([
                TranscribeMeetingJob::class,
                DiarizeMeetingJob::class,
            ]);
        });

        it('resets an existing transcription status to pending on retry', function (): void {
            Queue::fake();
            Storage::fake('local');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            $transcription = MeetingTranscription::factory()->failed()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Pending)
                ->and($transcription->fresh()->error_message)->toBeNull();
        });

        it('returns a success response after dispatching the job', function (): void {
            Queue::fake();
            Storage::fake('local');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 422 when no recording exists for the meeting', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns 422 when transcription is already processing', function (): void {
            Storage::fake('local');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('does not dispatch a job when transcription is already processing', function (): void {
            Queue::fake();
            Storage::fake('local');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            Queue::assertNotPushed(TranscribeMeetingJob::class);
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $response = $this->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            $response->assertUnauthorized();
        });
    });

    describe('retranscribe (POST /api/v1/meetings/{meeting}/transcription/retranscribe)', function (): void {
        it('clears existing transcription content and resets status', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            $transcription = MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'Old transcription.',
                'status' => TranscriptionStatus::Completed,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retranscribe"
            );

            $fresh = $transcription->fresh();
            expect($fresh->content)->toBeNull()
                ->and($fresh->status)->toBe(TranscriptionStatus::Pending);
        });

        it('dispatches a chained job for each recording in chronological order', function (): void {
            Bus::fake();
            Config::set('meetings.diarization.enabled', false);

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $first = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'created_at' => now()->subMinutes(10),
            ]);
            $second = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retranscribe"
            );

            Bus::assertChained([
                function (TranscribeMeetingJob $job) use ($first) {
                    return $job->recording->id === $first->id;
                },
                function (TranscribeMeetingJob $job) use ($second) {
                    return $job->recording->id === $second->id;
                },
            ]);
        });

        it('appends diarization job to the chain when diarization is enabled', function (): void {
            Bus::fake();
            Config::set('meetings.diarization.enabled', true);

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retranscribe"
            );

            Bus::assertChained([
                TranscribeMeetingJob::class,
                DiarizeMeetingJob::class,
            ]);
        });

        it('returns 422 when no recordings exist', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retranscribe"
            );

            $response->assertStatus(422);
        });

        it('returns 422 when transcription is already processing', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retranscribe"
            );

            $response->assertStatus(422);
        });
    });

    describe('storeManual (POST /api/v1/meetings/{meeting}/transcription/manual)', function (): void {
        it('creates a new transcription with the manual provider', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Manually entered transcription text.']
            );

            $this->assertDatabaseHas('meeting_transcriptions', [
                'meeting_id' => $meeting->id,
                'provider' => 'manual',
                'content' => 'Manually entered transcription text.',
            ]);
        });

        it('sets the status to completed when saving manual transcription', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.']
            );

            $transcription = MeetingTranscription::first();
            expect($transcription->status)->toBe(TranscriptionStatus::Completed);
        });

        it('updates an existing transcription instead of creating a duplicate', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->failed()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Corrected transcription.']
            );

            expect(MeetingTranscription::count())->toBe(1);

            $transcription = MeetingTranscription::first();
            expect($transcription->content)->toBe('Corrected transcription.')
                ->and($transcription->provider)->toBe('manual')
                ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
                ->and($transcription->error_message)->toBeNull();
        });

        it('uses the provided language when saving manual transcription', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'nl']);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.', 'language' => 'en']
            );

            expect(MeetingTranscription::first()->language)->toBe('en');
        });

        it('falls back to the meeting transcription_language when language is omitted', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'nl']);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.']
            );

            expect(MeetingTranscription::first()->language)->toBe('nl');
        });

        it('returns a success response after saving the manual transcription', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.']
            );

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 422 when content is missing', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                []
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['content']);
        });

        it('returns 422 when language is not an accepted value', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.', 'language' => 'de']
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['language']);
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.']
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $response = $this->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/manual",
                ['content' => 'Some content.']
            );

            $response->assertUnauthorized();
        });
    });

    describe('diarize (POST /api/v1/meetings/{meeting}/transcription/diarize)', function (): void {
        it('dispatches a diarization job when transcription is completed', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'content' => 'Some transcription text.',
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/diarize"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);

            Queue::assertPushed(DiarizeMeetingJob::class);
        });

        it('rejects diarization when transcription is not completed', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Processing,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/diarize"
            );

            $response->assertUnprocessable();
        });

        it('rejects diarization when no recording exists', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/diarize"
            );

            $response->assertUnprocessable();
        });

        it('rejects diarization when already in progress', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'diarization_status' => DiarizationStatus::Processing,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/diarize"
            );

            $response->assertUnprocessable();
        });

        it('sets diarization_status to pending when dispatching', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            $transcription = MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/diarize"
            );

            expect($transcription->fresh()->diarization_status)->toBe(DiarizationStatus::Pending);
        });
    });

    describe('retryDiarization (POST /api/v1/meetings/{meeting}/transcription/retry-diarization)', function (): void {
        it('dispatches a diarization job on retry', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'diarization_status' => DiarizationStatus::Failed,
                'diarization_error' => 'Connection timeout',
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry-diarization"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);

            Queue::assertPushed(DiarizeMeetingJob::class);
        });

        it('rejects retry when diarization is already in progress', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'diarization_status' => DiarizationStatus::Processing,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry-diarization"
            );

            $response->assertUnprocessable();
        });

        it('rejects retry when no transcription exists', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry-diarization"
            );

            $response->assertUnprocessable();
        });
    });

    describe('show includes diarization fields', function (): void {
        it('returns diarization_status and diarized_content in response', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => TranscriptionStatus::Completed,
                'diarization_status' => DiarizationStatus::Completed,
                'diarized_content' => '{"segments":[],"speakers":[]}',
            ]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/transcription"
            );

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'diarization_status',
                        'diarized_content',
                        'diarization_error',
                    ],
                ]);
        });
    });
});
