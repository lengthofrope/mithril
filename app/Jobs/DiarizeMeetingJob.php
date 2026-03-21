<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DiarizationStatus;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
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

        $transcription = $this->findTranscription();

        if ($transcription === null) {
            Log::warning('DiarizeMeetingJob: No transcription found, skipping.', [
                'meeting_id' => $this->meeting->id,
            ]);
            return;
        }

        $transcription->update([
            'diarization_status' => DiarizationStatus::Processing,
            'diarization_error' => null,
        ]);

        try {
            $audioPath = Storage::disk($this->recording->disk)->path($this->recording->path);
            $language = $this->meeting->transcription_language;

            $result = $diarizationService->diarize($audioPath, $language);

            $transcription->update([
                'diarized_content' => $result->toJson(),
                'content' => $result->toFormattedText(),
                'diarization_status' => DiarizationStatus::Completed,
                'diarization_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Diarization failed', [
                'meeting_id' => $this->meeting->id,
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);

            $transcription->update([
                'diarization_status' => DiarizationStatus::Failed,
                'diarization_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find the existing transcription for this meeting.
     *
     * @return MeetingTranscription|null
     */
    private function findTranscription(): ?MeetingTranscription
    {
        return MeetingTranscription::withoutGlobalScopes()
            ->where('meeting_id', $this->meeting->id)
            ->first();
    }
}
