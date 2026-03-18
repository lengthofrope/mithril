<?php

declare(strict_types=1);

use App\Enums\TranscriptionStatus;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                        'language',
                        'provider',
                        'error_message',
                        'updated_at',
                    ],
                ]);
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
            Storage::fake('local');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/transcription/retry"
            );

            Queue::assertPushed(TranscribeMeetingJob::class);
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
});
