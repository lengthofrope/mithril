<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

/**
 * Value object representing the result of an AI meeting insight extraction.
 */
class ExtractionResult
{
    /**
     * Create the extraction result.
     *
     * @param string $summary AI-generated meeting summary.
     * @param list<ExtractionItem> $items Extracted action items.
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $items,
    ) {}
}
