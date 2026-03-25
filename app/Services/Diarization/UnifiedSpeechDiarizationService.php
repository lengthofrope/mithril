<?php

declare(strict_types=1);

namespace App\Services\Diarization;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Diarization service using the unified mithril-speech container.
 *
 * Sends audio to the /diarize endpoint of the mithril-speech FastAPI
 * service and returns speaker-labeled transcription segments.
 */
class UnifiedSpeechDiarizationService implements DiarizationServiceInterface
{
    /**
     * Create the unified speech diarization service.
     *
     * @param string $baseUrl   Base URL of the mithril-speech service (e.g. http://localhost:8090).
     * @param string $authToken Optional authentication token sent as X-Speech-Token header.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $authToken = '',
    ) {}

    /**
     * Diarize an audio file using the unified speech service.
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
            $request = Http::timeout(1800);

            if ($this->authToken !== '') {
                $request = $request->withHeaders(['X-Speech-Token' => $this->authToken]);
            }

            $response = $request
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

        $data = $response->json();

        if (empty($data['segments'])) {
            throw new \RuntimeException('Unified speech service returned no diarization segments.');
        }

        return DiarizationResult::fromResponse($data);
    }
}
