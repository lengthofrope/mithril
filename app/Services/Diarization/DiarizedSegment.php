<?php

declare(strict_types=1);

namespace App\Services\Diarization;

/**
 * Value object representing a single speaker-labeled transcription segment.
 */
final readonly class DiarizedSegment
{
    /**
     * Create a diarized segment.
     *
     * @param string $speaker Speaker identifier (e.g. "SPEAKER_00").
     * @param float  $start   Start time in seconds.
     * @param float  $end     End time in seconds.
     * @param string $text    Transcribed text for this segment.
     */
    public function __construct(
        public string $speaker,
        public float $start,
        public float $end,
        public string $text,
    ) {}

    /**
     * Create a segment from an associative array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            speaker: (string) ($data['speaker'] ?? 'UNKNOWN'),
            start: (float) ($data['start'] ?? 0.0),
            end: (float) ($data['end'] ?? 0.0),
            text: (string) ($data['text'] ?? ''),
        );
    }

    /**
     * Convert the segment to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'speaker' => $this->speaker,
            'start' => $this->start,
            'end' => $this->end,
            'text' => $this->text,
        ];
    }
}
