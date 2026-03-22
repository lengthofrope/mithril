<?php

declare(strict_types=1);

namespace App\Services\Transcription;

/**
 * Contract for audio-to-text transcription providers.
 *
 * Implementations are responsible for sending audio to an external service
 * and returning the transcribed text.
 */
interface TranscriptionServiceInterface
{
    /**
     * Transcribe an audio file and return the text content.
     *
     * @param string $audioPath Absolute path to the audio file on disk.
     * @param string $language  BCP-47 language code (e.g. 'nl', 'en').
     * @return string The transcribed text.
     * @throws \RuntimeException When the transcription fails.
     */
    public function transcribe(string $audioPath, string $language): string;
}
