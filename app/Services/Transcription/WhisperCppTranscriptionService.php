<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Transcription service using a self-hosted whisper.cpp server.
 *
 * Sends audio to the /inference endpoint of a local whisper.cpp server
 * and returns the transcribed text. No external API keys required.
 */
class WhisperCppTranscriptionService implements TranscriptionServiceInterface
{
    /**
     * Create the whisper.cpp transcription service.
     *
     * @param string $baseUrl Base URL of the whisper.cpp server (e.g. http://localhost:8080).
     */
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Transcribe an audio file using a self-hosted whisper.cpp server.
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

        $url = rtrim($this->baseUrl, '/') . '/inference';

        try {
            $response = Http::timeout(600)
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post($url, [
                    'language' => $language,
                    'response_format' => 'json',
                    'temperature' => '0.0',
                    'temperature_inc' => '0.2',
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("whisper.cpp server connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            throw new \RuntimeException("whisper.cpp server error ({$response->status()}): {$response->body()}");
        }

        $text = trim($response->json('text') ?? $response->body());

        if ($text === '') {
            throw new \RuntimeException('whisper.cpp server returned an empty transcription.');
        }

        return $text;
    }
}
