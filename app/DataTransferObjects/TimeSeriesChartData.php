<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Immutable data transfer object carrying time-series chart labels, named series, and optional colour overrides.
 *
 * Designed for line charts where each series represents a named metric tracked over a sequence of dates.
 */
final readonly class TimeSeriesChartData
{
    /**
     * @param array<int, string>                                             $labels ISO date strings for the x-axis.
     * @param array<int, array{name: string, data: array<int, int|float>}>  $series Named data series, each with a name and a value per label.
     * @param array<int, string>                                             $colors Optional hex colour strings per series.
     */
    public function __construct(
        public array $labels,
        public array $series,
        public array $colors = [],
    ) {}
}
