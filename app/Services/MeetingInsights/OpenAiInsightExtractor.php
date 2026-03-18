<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Extracts meeting insights using the OpenAI Chat Completions API.
 *
 * Sends the transcription with a structured prompt and parses the JSON response
 * into a summary and list of extraction items.
 */
class OpenAiInsightExtractor implements MeetingInsightExtractorInterface
{
    /**
     * Create the extractor.
     *
     * @param string $apiKey OpenAI API key.
     * @param string $model  Model to use (e.g. gpt-4o, gpt-4o-mini).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

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
        $prompt = $this->buildPrompt($transcription, $attendees, $meetingTitle, $language);

        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->buildSystemPrompt($language)],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("OpenAI API connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("OpenAI API error ({$response->status()}): {$error}");
        }

        $content = $response->json('choices.0.message.content', '');
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            throw new \RuntimeException('OpenAI API returned invalid JSON.');
        }

        return $this->parseResult($parsed);
    }

    /**
     * Build the system prompt instructing the model's role and output format.
     *
     * @param string $language
     * @return string
     */
    private function buildSystemPrompt(string $language): string
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
    private function buildPrompt(
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
     * Parse the API response into an ExtractionResult.
     *
     * @param array<string, mixed> $parsed
     * @return ExtractionResult
     */
    private function parseResult(array $parsed): ExtractionResult
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
