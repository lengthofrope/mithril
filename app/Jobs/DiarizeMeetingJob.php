<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DiarizationStatus;
use App\Enums\TranscriptionStatus;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\ProcessingTimingLog;
use App\Services\Diarization\DiarizationServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that performs speaker diarization on a meeting recording.
 *
 * Runs after TranscribeMeetingJob to enrich the transcription with speaker
 * labels. On success, overwrites the plain-text content with speaker-labeled
 * text and stores the structured segments in diarized_content.
 */
class DiarizeMeetingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of retry attempts.
     */
    public int $tries = 2;

    /**
     * Disable worker-level timeout; the HTTP client timeout handles failures.
     */
    public int $timeout = 0;

    /**
     * Seconds to wait between retries (backoff).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Create the job.
     *
     * @param Meeting          $meeting   The meeting to diarize.
     * @param MeetingRecording $recording The recording to use as audio source.
     */
    public function __construct(
        public readonly Meeting $meeting,
        public readonly MeetingRecording $recording,
    ) {}

    /**
     * Execute the job.
     *
     * @param DiarizationServiceInterface $diarizationService
     * @return void
     */
    public function handle(DiarizationServiceInterface $diarizationService): void
    {
        if (!config('meetings.transcription.enabled', true)) {
            Log::info('DiarizeMeetingJob: transcription feature disabled, skipping.', [
                'meeting_id' => $this->meeting->id,
            ]);
            return;
        }

        $transcription = $this->findOrCreateTranscription();

        $startedAt = now();

        $transcription->update([
            'status' => TranscriptionStatus::Processing,
            'diarization_status' => DiarizationStatus::Processing,
            'diarization_error' => null,
            'diarization_started_at' => $startedAt,
            'processing_started_at' => $transcription->processing_started_at ?? $startedAt,
            'audio_duration_seconds' => $this->recording->duration_seconds,
        ]);

        try {
            $audioPath = Storage::disk($this->recording->disk)->path($this->recording->path);
            $language = $this->meeting->transcription_language;

            $result = $diarizationService->diarize($audioPath, $language);

            $durationSeconds = (int) $startedAt->diffInSeconds(now());

            $transcription->update([
                'diarized_content' => $result->toJson(),
                'content' => $result->toFormattedText(),
                'status' => TranscriptionStatus::Completed,
                'diarization_status' => DiarizationStatus::Completed,
                'diarization_error' => null,
                'error_message' => null,
                'processing_duration_seconds' => $durationSeconds,
                'diarization_duration_seconds' => $durationSeconds,
            ]);

            if ($transcription->audio_duration_seconds) {
                ProcessingTimingLog::create([
                    'user_id' => $transcription->user_id,
                    'type' => 'diarization',
                    'audio_duration_seconds' => $transcription->audio_duration_seconds,
                    'processing_duration_seconds' => $durationSeconds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Diarization failed', [
                'meeting_id' => $this->meeting->id,
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);

            $transcription->update([
                'status' => TranscriptionStatus::Failed,
                'diarization_status' => DiarizationStatus::Failed,
                'diarization_error' => $e->getMessage(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find the existing transcription or create a new pending one.
     *
     * @return MeetingTranscription
     */
    private function findOrCreateTranscription(): MeetingTranscription
    {
        $existing = MeetingTranscription::withoutGlobalScopes()
            ->where('meeting_id', $this->meeting->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return MeetingTranscription::forceCreate([
            'user_id' => $this->meeting->user_id,
            'meeting_id' => $this->meeting->id,
            'language' => $this->meeting->transcription_language,
            'provider' => 'unified',
            'status' => TranscriptionStatus::Pending,
        ]);
    }
}
