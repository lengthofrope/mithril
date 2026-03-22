<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Extracts meeting insights using the Anthropic Messages API.
 */
class AnthropicInsightExtractor extends AbstractInsightExtractor
{
    private const API_VERSION = '2023-06-01';

    /**
     * Create the extractor.
     *
     * @param string $apiKey Anthropic API key.
     * @param string $model  Model to use (e.g. claude-haiku-4-5).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-haiku-4-5',
    ) {}

    /**
     * Send the prompt to the Anthropic Messages API.
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
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'temperature' => 0.3,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                        ['role' => 'assistant', 'content' => '{'],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Anthropic API connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("Anthropic API error ({$response->status()}): {$error}");
        }

        $content = '{' . $response->json('content.0.text', '');
        $parsed = json_decode($this->extractJson($content), true);

        if (!is_array($parsed)) {
            throw new \RuntimeException('Anthropic API returned invalid JSON: ' . mb_substr($content, 0, 200));
        }

        return $parsed;
    }
}
