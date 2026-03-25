<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

/**
 * Contract for AI-powered meeting insight extraction.
 *
 * Implementations analyze a meeting transcription and return structured
 * extractions (tasks, follow-ups, agreements, decisions) plus a summary.
 */
interface MeetingInsightExtractorInterface
{
    /**
     * Extract structured insights from a meeting transcription.
     *
     * @param string $transcription The full transcription text.
     * @param array<int, array{id: int, name: string}> $attendees List of attendees with id and name.
     * @param string $meetingTitle The meeting title for context.
     * @param string $language Output language for the extracted content (e.g. 'nl', 'en').
     * @return ExtractionResult The extracted summary and items.
     * @throws \RuntimeException When the extraction fails.
     */
    public function extract(
        string $transcription,
        array $attendees,
        string $meetingTitle,
        string $language,
    ): ExtractionResult;
}
