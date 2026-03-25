<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Extracts meeting insights using the OpenAI Chat Completions API.
 */
class OpenAiInsightExtractor extends AbstractInsightExtractor
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
     * Send the prompt to the OpenAI Chat Completions API.
     *
     * @param string $systemPrompt The system prompt.
     * @param string $userPrompt The user prompt.
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    protected function callApi(string $systemPrompt, string $userPrompt): array
    {
        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
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
        $parsed = json_decode($this->extractJson($content), true);

        if (!is_array($parsed)) {
            throw new \RuntimeException('OpenAI API returned invalid JSON: ' . mb_substr($content, 0, 200));
        }

        return $parsed;
    }
}
