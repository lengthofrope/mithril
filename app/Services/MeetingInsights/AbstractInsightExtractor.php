<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

/**
 * Base class for AI-powered meeting insight extractors.
 *
 * Contains shared prompt building and response parsing logic.
 * Concrete implementations only need to provide the HTTP call to their respective API.
 */
abstract class AbstractInsightExtractor implements MeetingInsightExtractorInterface
{
    /**
     * Send the prompt to the AI provider and return the raw JSON-decoded response.
     *
     * @param string $systemPrompt The system prompt.
     * @param string $userPrompt The user prompt.
     * @return array<string, mixed> The parsed JSON response.
     * @throws \RuntimeException When the API call fails.
     */
    abstract protected function callApi(string $systemPrompt, string $userPrompt): array;

    /**
     * Extract structured insights from a meeting transcription.
     *
     * @param string $transcription The full transcription text.
     * @param array<int, array{id: int, name: string}> $attendees Attendees.
     * @param string $meetingTitle Meeting title for context.
     * @param string $language Output language.
     * @return ExtractionResult
     * @throws \RuntimeException
     */
    public function extract(
        string $transcription,
        array $attendees,
        string $meetingTitle,
        string $language,
    ): ExtractionResult {
        $systemPrompt = $this->buildSystemPrompt($language);
        $userPrompt = $this->buildPrompt($transcription, $attendees, $meetingTitle, $language);

        $parsed = $this->callApi($systemPrompt, $userPrompt);

        return $this->parseResult($parsed);
    }

    /**
     * Build the system prompt instructing the model's role and output format.
     *
     * @param string $language
     * @return string
     */
    protected function buildSystemPrompt(string $language): string
    {
        $langName = $language === 'nl' ? 'Dutch' : 'English';

        return <<<PROMPT
        You are a meeting analysis assistant. You extract actionable items from meeting transcriptions.

        Always respond in {$langName}. Return a JSON object with this exact structure:
        {
            "summary": "A concise 2-3 sentence summary of the meeting",
            "items": [
                {
                    "type": "task|follow_up|agreement|decision",
                    "content": "Description of the item",
                    "assignee_id": null or integer (team member ID if identifiable),
                    "priority": "urgent|high|normal|low" or null,
                    "deadline": "YYYY-MM-DD" or null
                }
            ]
        }

        Guidelines:
        - "task": Concrete work items that need to be done
        - "follow_up": Items that need to be checked on later
        - "agreement": Mutual agreements or commitments made
        - "decision": Important decisions that were made
        - Only assign an assignee_id if the attendee is clearly responsible
        - Only set a deadline if one was explicitly mentioned
        - Keep content concise but complete
        PROMPT;
    }

    /**
     * Build the user prompt with transcription and context.
     *
     * @param string $transcription
     * @param array<int, array{id: int, name: string}> $attendees
     * @param string $meetingTitle
     * @param string $language
     * @return string
     */
    protected function buildPrompt(
        string $transcription,
        array $attendees,
        string $meetingTitle,
        string $language,
    ): string {
        $attendeeList = collect($attendees)
            ->map(fn (array $a) => "- {$a['name']} (ID: {$a['id']})")
            ->implode("\n");

        return <<<PROMPT
        Meeting: {$meetingTitle}
        Language: {$language}

        Attendees:
        {$attendeeList}

        Transcription:
        {$transcription}
        PROMPT;
    }

    /**
     * Extract a JSON object from a string that may contain markdown fences or preamble.
     *
     * @param string $content
     * @return string
     */
    protected function extractJson(string $content): string
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\{[\s\S]*\})\s*$/', $content, $matches)) {
            return $matches[1];
        }

        return $content;
    }

    /**
     * Parse the API response into an ExtractionResult.
     *
     * @param array<string, mixed> $parsed
     * @return ExtractionResult
     */
    protected function parseResult(array $parsed): ExtractionResult
    {
        $summary = $parsed['summary'] ?? '';
        $rawItems = $parsed['items'] ?? [];

        $items = array_map(
            fn (array $item) => ExtractionItem::fromArray($item),
            is_array($rawItems) ? $rawItems : [],
        );

        return new ExtractionResult($summary, $items);
    }
}
