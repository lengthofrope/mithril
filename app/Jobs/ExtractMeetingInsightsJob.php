<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that extracts insights from a meeting transcription via AI.
 *
 * Saves the AI-generated summary to the meeting and creates MeetingExtraction
 * records for each extracted item in pending status.
 */
class ExtractMeetingInsightsJob implements ShouldQueue
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
     * Seconds to wait between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180];
    }

    /**
     * Create the job.
     *
     * @param Meeting $meeting The meeting whose transcription to analyze.
     */
    public function __construct(
        public readonly Meeting $meeting,
    ) {}

    /**
     * Execute the job.
     *
     * @param MeetingInsightExtractorInterface $extractor
     * @return void
     */
    public function handle(MeetingInsightExtractorInterface $extractor): void
    {
        $transcription = $this->meeting->transcription;

        if ($transcription === null || $transcription->content === null) {
            Log::warning('ExtractMeetingInsightsJob: no transcription content', [
                'meeting_id' => $this->meeting->id,
            ]);
            return;
        }

        $attendees = $this->meeting->attendees->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->all();

        $outputLanguage = $this->meeting->output_language
            ?? $this->meeting->user?->preferred_output_language
            ?? 'nl';

        try {
            $result = $extractor->extract(
                transcription: $transcription->content,
                attendees: $attendees,
                meetingTitle: $this->meeting->title,
                language: $outputLanguage,
            );

            $this->meeting->update(['summary' => $result->summary]);

            foreach ($result->items as $item) {
                MeetingExtraction::forceCreate([
                    'user_id' => $this->meeting->user_id,
                    'meeting_id' => $this->meeting->id,
                    'type' => $item->type,
                    'content' => $item->content,
                    'assignee_id' => $item->assigneeId,
                    'priority' => $item->priority,
                    'deadline' => $item->deadline,
                    'status' => ExtractionStatus::Pending,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Meeting insight extraction failed', [
                'meeting_id' => $this->meeting->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
