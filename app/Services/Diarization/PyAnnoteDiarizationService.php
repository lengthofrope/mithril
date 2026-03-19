<?php

declare(strict_types=1);

namespace App\Services\Diarization;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Diarization service using a self-hosted pyannote-audio FastAPI server.
 *
 * Sends audio to the /diarize endpoint which runs speaker diarization
 * (pyannote) and timestamped transcription (faster-whisper) internally,
 * returning merged speaker-labeled segments.
 */
class PyAnnoteDiarizationService implements DiarizationServiceInterface
{
    /**
     * Create the pyannote diarization service.
     *
     * @param string $baseUrl Base URL of the pyannote server (e.g. http://localhost:8081).
     */
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Diarize an audio file using the self-hosted pyannote service.
     *
     * @param string $audioPath Absolute path to the audio file.
     * @param string $language  BCP-47 language code (e.g. 'nl', 'en').
     * @return DiarizationResult Speaker-labeled transcription segments.
     * @throws \RuntimeException When the server call fails or returns an error.
     */
    public function diarize(string $audioPath, string $language): DiarizationResult
    {
        if (!file_exists($audioPath)) {
            throw new \RuntimeException("Audio file not found: {$audioPath}");
        }

        $url = rtrim($this->baseUrl, '/') . '/diarize';

        try {
            $response = Http::timeout(1800)
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post($url, [
                    'language' => $language,
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Pyannote server connection failed: {$e->getMessage()}", 0, $e);
        }

        if (!$response->successful()) {
            throw new \RuntimeException("Pyannote server error ({$response->status()}): {$response->body()}");
        }

        $data = $response->json();

        if (empty($data['segments'])) {
            throw new \RuntimeException('Pyannote server returned no diarization segments.');
        }

        return DiarizationResult::fromResponse($data);
    }
}
