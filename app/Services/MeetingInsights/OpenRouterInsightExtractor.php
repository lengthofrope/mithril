<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Extracts meeting insights using the OpenRouter API (OpenAI-compatible).
 */
class OpenRouterInsightExtractor extends AbstractInsightExtractor
{
    /**
     * Create the extractor.
     *
     * @param string $apiKey OpenRouter API key.
     * @param string $model  Model to use (e.g. openai/gpt-4o-mini).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'openai/gpt-4o-mini',
    ) {}

    /**
     * Send the prompt to the OpenRouter Chat Completions API.
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
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => config('app.name', 'Mithril'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("OpenRouter API connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("OpenRouter API error ({$response->status()}): {$error}");
        }

        $content = $response->json('choices.0.message.content', '');
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            throw new \RuntimeException('OpenRouter API returned invalid JSON.');
        }

        return $parsed;
    }
}
