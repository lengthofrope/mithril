<?php

declare(strict_types=1);

use App\Enums\DiarizationStatus;
use App\Jobs\DiarizeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\User;
use App\Services\Diarization\DiarizationResult;
use App\Services\Diarization\DiarizationServiceInterface;
use App\Services\Diarization\DiarizedSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Build a DiarizationResult with two speakers for use in success-path tests.
 */
function makeDiarizationResult(): DiarizationResult
{
    return new DiarizationResult(
        segments: [
            new DiarizedSegment(speaker: 'SPEAKER_00', start: 0.0, end: 5.0, text: 'Hello there.'),
            new DiarizedSegment(speaker: 'SPEAKER_01', start: 5.5, end: 10.0, text: 'Hi, how are you?'),
        ],
        speakers: ['SPEAKER_00', 'SPEAKER_01'],
    );
}

describe('DiarizeMeetingJob', function (): void {
    describe('handle — success path', function (): void {
        it('stores diarized_content as JSON and updates content with formatted text on success', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $result = makeDiarizationResult();

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andReturn($result);

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'nl']);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'Original whisper content.',
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();

            expect($transcription->diarization_status)->toBe(DiarizationStatus::Completed)
                ->and($transcription->diarized_content)->toBe($result->toJson())
                ->and($transcription->content)->toBe($result->toFormattedText());
        });

        it('sets diarization_status to processing before calling the diarization service', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $capturedStatus = null;

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andReturnUsing(function () use (&$capturedStatus): DiarizationResult {
                $capturedStatus = MeetingTranscription::withoutGlobalScopes()->first()?->diarization_status;

                return makeDiarizationResult();
            });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            expect($capturedStatus)->toBe(DiarizationStatus::Processing);
        });

        it('clears diarization_error on success', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andReturn(makeDiarizationResult());

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'diarization_status' => DiarizationStatus::Failed,
                'diarization_error' => 'Previous error message.',
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->diarization_error)->toBeNull();
        });
    });

    describe('handle — failure path', function (): void {
        it('sets diarization_status to failed when the service throws', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andThrow(new \RuntimeException('Diarization service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'Original whisper content.',
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->diarization_status)->toBe(DiarizationStatus::Failed);
        });

        it('stores the exception message as diarization_error on failure', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andThrow(new \RuntimeException('Diarization service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->diarization_error)->toBe('Diarization service unavailable');
        });

        it('preserves the original whisper content when diarization fails', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andThrow(new \RuntimeException('Diarization service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'Original whisper content.',
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->content)->toBe('Original whisper content.');
        });

        it('does not throw when the diarization service fails', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->once()->andThrow(new \RuntimeException('Diarization service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            expect(fn () => (new DiarizeMeetingJob($meeting, $recording))->handle($mock))->not->toThrow(\Throwable::class);
        });
    });

    describe('handle — missing transcription', function (): void {
        it('skips gracefully and logs a warning when no transcription exists', function (): void {
            /** @var \Tests\TestCase $this */
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            Log::shouldReceive('warning')
                ->once()
                ->with('DiarizeMeetingJob: No transcription found, skipping.', \Mockery::type('array'));

            $mock = $this->mock(DiarizationServiceInterface::class);
            $mock->shouldReceive('diarize')->never();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new DiarizeMeetingJob($meeting, $recording))->handle($mock);

            expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(0);
        });
    });

    describe('job configuration', function (): void {
        it('is configured with 2 retry attempts', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $job = new DiarizeMeetingJob($meeting, $recording);

            expect($job->tries)->toBe(2);
        });

        it('is configured with backoff intervals of 60 and 300 seconds', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $job = new DiarizeMeetingJob($meeting, $recording);

            expect($job->backoff())->toBe([60, 300]);
        });
    });
});
