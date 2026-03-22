<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Transcription service using the unified mithril-speech container.
 *
 * Sends audio to the /transcribe endpoint of the mithril-speech FastAPI
 * service and returns the transcribed text.
 */
class UnifiedSpeechTranscriptionService implements TranscriptionServiceInterface
{
    /**
     * Create the unified speech transcription service.
     *
     * @param string $baseUrl Base URL of the mithril-speech service (e.g. http://localhost:8090).
     */
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Transcribe an audio file using the unified speech service.
     *
     * @param string $audioPath Absolute path to the audio file.
     * @param string $language  BCP-47 language code (e.g. 'nl', 'en').
     * @return string The transcribed text.
     * @throws \RuntimeException When the server call fails or returns an error.
     */
    public function transcribe(string $audioPath, string $language): string
    {
        if (!file_exists($audioPath)) {
            throw new \RuntimeException("Audio file not found: {$audioPath}");
        }

        $url = rtrim($this->baseUrl, '/') . '/transcribe';

        try {
            $response = Http::timeout(600)
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post($url, [
                    'language' => $language,
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Unified speech service connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            throw new \RuntimeException("Unified speech service error ({$response->status()}): {$response->body()}");
        }

        $text = trim($response->json('text') ?? '');

        if ($text === '') {
            throw new \RuntimeException('Unified speech service returned an empty transcription.');
        }

        return $text;
    }
}
