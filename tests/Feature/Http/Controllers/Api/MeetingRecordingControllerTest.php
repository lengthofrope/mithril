<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\SpeechServiceMode;
use App\Jobs\DiarizeMeetingJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('MeetingRecordingController', function (): void {
    describe('store (POST /api/v1/meetings/{meeting}/recordings)', function (): void {
        it('uploads an audio file and returns 201 with recording data', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertStatus(201)
                ->assertJson(['success' => true]);
        });

        it('saves the recording file to disk and creates a DB record', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $recording = MeetingRecording::first();
            expect($recording)->not->toBeNull()
                ->and($recording->meeting_id)->toBe($meeting->id)
                ->and($recording->mime_type)->not->toBeEmpty()
                ->and($recording->size_bytes)->toBeGreaterThan(0);

            Storage::disk('local')->assertExists($recording->path);
        });

        it('returns recording data in the response body', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'meeting_id',
                        'disk',
                        'path',
                        'mime_type',
                        'size_bytes',
                    ],
                ]);
        });

        it('auto-transitions meeting from scheduled to in_progress on first recording', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create([
                'user_id' => $user->id,
                'status' => MeetingStatus::Scheduled,
            ]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            expect($meeting->fresh()->status)->toBe(MeetingStatus::InProgress);
        });

        it('does not auto-transition meeting when it is already in_progress', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create([
                'user_id' => $user->id,
                'status' => MeetingStatus::InProgress,
                'started_at' => now()->subMinutes(10),
            ]);

            $originalStartedAt = $meeting->started_at;

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $fresh = $meeting->fresh();
            expect($fresh->status)->toBe(MeetingStatus::InProgress)
                ->and($fresh->started_at->timestamp)->toBe($originalStartedAt->timestamp);
        });

        it('saves duration_seconds when provided', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file, 'duration_seconds' => 120]
            );

            expect(MeetingRecording::first()->duration_seconds)->toBe(120);
        });

        it('validates that the audio field is required', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                []
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['audio']);
        });

        it('rejects non-audio files such as PDF', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $file = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['audio']);
        });

        it('rejects files that exceed the configured size limit', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $maxMb = (int) config('meetings.recording.max_upload_mb', 500);
            $oversizeKb = ($maxMb * 1024) + 1024;

            $file = UploadedFile::fake()->create('recording.webm', $oversizeKb, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['audio']);
        });

        it('rejects upload when user storage quota is exceeded', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $maxBytes = config('attachments.max_storage_mb') * 1024 * 1024;

            MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_big.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => $maxBytes,
            ]);

            $file = UploadedFile::fake()->create('recording.webm', 512, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns 404 when meeting belongs to another user', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $response = $this->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm')]
            );

            $response->assertUnauthorized();
        });
    });

    describe('store — mode-aware dispatch', function (): void {
        it('dispatches a transcription job in server mode', function (): void {
            Bus::fake([TranscribeMeetingJob::class, DiarizeMeetingJob::class]);
            Storage::fake('local');

            config([
                'meetings.speech.server_enabled' => true,
                'meetings.transcription.auto_start' => true,
                'meetings.diarization.enabled' => false,
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Server,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            Bus::assertDispatched(TranscribeMeetingJob::class);
        });

        it('does not dispatch any job in local mode', function (): void {
            Bus::fake([TranscribeMeetingJob::class, DiarizeMeetingJob::class]);
            Storage::fake('local');

            config([
                'meetings.speech.server_enabled' => true,
                'meetings.transcription.auto_start' => true,
                'meetings.diarization.enabled' => false,
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            Bus::assertNotDispatched(TranscribeMeetingJob::class);
            Bus::assertNotDispatched(DiarizeMeetingJob::class);
        });

        it('includes processing_mode local in response when user is in local mode', function (): void {
            Bus::fake([TranscribeMeetingJob::class, DiarizeMeetingJob::class]);
            Storage::fake('local');

            config([
                'meetings.speech.server_enabled' => true,
                'meetings.transcription.auto_start' => true,
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertStatus(201)
                ->assertJsonPath('processing_mode', 'local');
        });

        it('includes processing_mode server in response when user is in server mode', function (): void {
            Bus::fake([TranscribeMeetingJob::class, DiarizeMeetingJob::class]);
            Storage::fake('local');

            config([
                'meetings.speech.server_enabled' => true,
                'meetings.transcription.auto_start' => true,
                'meetings.diarization.enabled' => false,
                'meetings.custom_url_enabled' => true,
            ]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Server,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            $response->assertStatus(201)
                ->assertJsonPath('processing_mode', 'server');
        });

        it('dispatches job when custom_url_enabled is false regardless of user mode', function (): void {
            Bus::fake([TranscribeMeetingJob::class, DiarizeMeetingJob::class]);
            Storage::fake('local');

            config([
                'meetings.speech.server_enabled' => true,
                'meetings.transcription.auto_start' => true,
                'meetings.diarization.enabled' => false,
                'meetings.custom_url_enabled' => false,
            ]);

            $user = User::factory()->create([
                'speech_service_mode' => SpeechServiceMode::Local,
            ]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $file = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/recordings",
                ['audio' => $file]
            );

            Bus::assertDispatched(TranscribeMeetingJob::class);
        });
    });

    describe('destroy (DELETE /api/v1/meetings/{meeting}/recordings/{recording})', function (): void {
        it('deletes a recording and returns a success response', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            Storage::disk('local')->put('recordings/2026/03/rec_test.webm', 'fake audio');

            $recording = MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_test.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 1024,
            ]);

            $response = $this->actingAs($user)->deleteJson(
                "/api/v1/meetings/{$meeting->id}/recordings/{$recording->id}"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('meeting_recordings', ['id' => $recording->id]);
        });

        it('removes the audio file from disk when a recording is deleted', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            Storage::disk('local')->put('recordings/2026/03/rec_test.webm', 'fake audio');

            $recording = MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_test.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 1024,
            ]);

            $this->actingAs($user)->deleteJson(
                "/api/v1/meetings/{$meeting->id}/recordings/{$recording->id}"
            );

            Storage::disk('local')->assertMissing('recordings/2026/03/rec_test.webm');
        });

        it('returns 404 when recording does not belong to the given meeting', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);

            $recording = MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meetingA->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_test.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 1024,
            ]);

            $response = $this->actingAs($user)->deleteJson(
                "/api/v1/meetings/{$meetingB->id}/recordings/{$recording->id}"
            );

            $response->assertNotFound();
        });

        it('returns 404 when recording belongs to another user (BelongsToUser scope)', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $recording = MeetingRecording::create([
                'user_id' => $otherUser->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_other.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 1024,
            ]);

            $response = $this->actingAs($user)->deleteJson(
                "/api/v1/meetings/{$meeting->id}/recordings/{$recording->id}"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $recording = MeetingRecording::factory()->create();
            $meeting = $recording->meeting;

            $response = $this->deleteJson(
                "/api/v1/meetings/{$meeting->id}/recordings/{$recording->id}"
            );

            $response->assertUnauthorized();
        });
    });
});
