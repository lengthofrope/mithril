<?php

declare(strict_types=1);

namespace App\Services\Diarization;

/**
 * Value object holding the complete result of a speaker diarization.
 *
 * Contains structured segments (speaker + timestamps + text) and a list
 * of unique speaker identifiers found in the audio.
 */
final readonly class DiarizationResult
{
    /**
     * Create a diarization result.
     *
     * @param array<DiarizedSegment> $segments Ordered list of speaker-labeled segments.
     * @param array<string>          $speakers Unique speaker identifiers.
     */
    public function __construct(
        public array $segments,
        public array $speakers,
    ) {}

    /**
     * Create a result from the diarization service JSON response.
     *
     * @param array<string, mixed> $data Decoded JSON from the diarization service.
     * @return self
     */
    public static function fromResponse(array $data): self
    {
        $segments = array_map(
            fn (array $seg) => DiarizedSegment::fromArray($seg),
            $data['segments'] ?? [],
        );

        return new self(
            segments: $segments,
            speakers: $data['speakers'] ?? [],
        );
    }

    /**
     * Deserialize from a JSON string (stored in diarized_content column).
     *
     * @param string $json
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromResponse($data);
    }

    /**
     * Serialize to a JSON string for storage in the diarized_content column.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'segments' => array_map(fn (DiarizedSegment $seg) => $seg->toArray(), $this->segments),
            'speakers' => $this->speakers,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Format as human-readable speaker-labeled text for the content column.
     *
     * Produces output like:
     *   [SPEAKER_00]
     *   Hello, how are you?
     *
     *   [SPEAKER_01]
     *   I'm fine, thanks.
     *
     * @return string
     */
    public function toFormattedText(): string
    {
        if (count($this->segments) === 0) {
            return '';
        }

        $lines = [];

        foreach ($this->segments as $segment) {
            $lines[] = "[{$segment->speaker}]";
            $lines[] = $segment->text;
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}
