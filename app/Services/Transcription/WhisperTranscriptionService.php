<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Transcription service using the OpenAI Whisper API.
 *
 * Sends audio to the OpenAI /v1/audio/transcriptions endpoint and returns
 * the transcribed text. Requires an OpenAI API key in the configuration.
 */
class WhisperTranscriptionService implements TranscriptionServiceInterface
{
    /**
     * OpenAI Whisper API endpoint.
     */
    private const API_URL = 'https://api.openai.com/v1/audio/transcriptions';

    /**
     * Maximum file size supported by the Whisper API (25 MB).
     */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /**
     * Create the Whisper transcription service.
     *
     * @param string $apiKey OpenAI API key.
     * @param string $model  Whisper model to use (default: whisper-1).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'whisper-1',
    ) {}

    /**
     * Transcribe an audio file using the OpenAI Whisper API.
     *
     * @param string $audioPath Absolute path to the audio file.
     * @param string $language  BCP-47 language code (e.g. 'nl', 'en').
     * @return string The transcribed text.
     * @throws \RuntimeException When the API call fails or returns an error.
     */
    public function transcribe(string $audioPath, string $language): string
    {
        if (!file_exists($audioPath)) {
            throw new \RuntimeException("Audio file not found: {$audioPath}");
        }

        if (filesize($audioPath) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Audio file exceeds the 25 MB limit for the Whisper API.');
        }

        try {
            $response = Http::timeout(300)
                ->withToken($this->apiKey)
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'language' => $language,
                    'response_format' => 'text',
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Whisper API connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("Whisper API error ({$response->status()}): {$error}");
        }

        $text = trim($response->body());

        if ($text === '') {
            throw new \RuntimeException('Whisper API returned an empty transcription.');
        }

        return $text;
    }
}
