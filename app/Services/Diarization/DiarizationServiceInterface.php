<?php

declare(strict_types=1);

namespace App\Services\Diarization;

/**
 * Contract for speaker diarization providers.
 *
 * Implementations send audio to an external diarization service and return
 * speaker-labeled transcription segments.
 */
interface DiarizationServiceInterface
{
    /**
     * Diarize an audio file and return speaker-labeled segments.
     *
     * @param string $audioPath Absolute path to the audio file on disk.
     * @param string $language  BCP-47 language code (e.g. 'nl', 'en').
     * @return DiarizationResult The diarized transcription with speaker labels.
     * @throws \RuntimeException When the diarization fails.
     */
    public function diarize(string $audioPath, string $language): DiarizationResult;
}
