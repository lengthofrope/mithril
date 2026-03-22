<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TranscriptionStatus;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\ProcessingTimingLog;
use App\Services\Transcription\TranscriptionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that transcribes a meeting recording via the configured provider.
 *
 * Creates a MeetingTranscription record and updates its status through the
 * pending → processing → completed/failed lifecycle.
 */
class TranscribeMeetingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of retry attempts.
     */
    public int $tries = 3;

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
        return [30, 120, 300];
    }

    /**
     * Create the job.
     *
     * @param Meeting          $meeting   The meeting to transcribe.
     * @param MeetingRecording $recording The recording to use as audio source.
     */
    public function __construct(
        public readonly Meeting $meeting,
        public readonly MeetingRecording $recording,
    ) {}

    /**
     * Execute the job.
     *
     * @param TranscriptionServiceInterface $transcriptionService
     * @return void
     */
    public function handle(TranscriptionServiceInterface $transcriptionService): void
    {
        if (!config('meetings.transcription.enabled', true)) {
            Log::info('TranscribeMeetingJob: transcription feature disabled, skipping.', [
                'meeting_id' => $this->meeting->id,
            ]);
            return;
        }

        $transcription = $this->findOrCreateTranscription();

        $startedAt = now();

        $transcription->update([
            'status' => TranscriptionStatus::Processing,
            'processing_started_at' => $startedAt,
            'audio_duration_seconds' => $this->recording->duration_seconds,
        ]);

        try {
            $audioPath = Storage::disk($this->recording->disk)->path($this->recording->path);
            $language = $this->meeting->transcription_language;

            $text = $transcriptionService->transcribe($audioPath, $language);

            $existingContent = trim($transcription->content ?? '');
            $newContent = $existingContent !== ''
                ? $existingContent . "\n\n" . $text
                : $text;

            $durationSeconds = (int) $startedAt->diffInSeconds(now());

            $transcription->update([
                'content' => $newContent,
                'status' => TranscriptionStatus::Completed,
                'error_message' => null,
                'processing_duration_seconds' => $durationSeconds,
            ]);

            if ($transcription->audio_duration_seconds) {
                ProcessingTimingLog::create([
                    'user_id' => $transcription->user_id,
                    'type' => 'transcription',
                    'audio_duration_seconds' => $transcription->audio_duration_seconds,
                    'processing_duration_seconds' => $durationSeconds,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Transcription failed', [
                'meeting_id' => $this->meeting->id,
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);

            $transcription->update([
                'status' => TranscriptionStatus::Failed,
                'error_message' => $e->getMessage(),
                'processing_duration_seconds' => (int) $startedAt->diffInSeconds(now()),
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
            'provider' => config('meetings.transcription.provider', 'whisper'),
            'status' => TranscriptionStatus::Pending,
        ]);
    }
}
