<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Immutable data transfer object carrying chart labels, series values, and optional color overrides.
 */
final readonly class ChartData
{
    /**
     * @param array<int, string>       $labels  Human-readable category labels for each data point.
     * @param array<int, int|float>    $series  Numeric values corresponding to each label.
     * @param array<int, string>       $colors  Optional hex or CSS colour strings per series entry.
     */
    public function __construct(
        public array $labels,
        public array $series,
        public array $colors = [],
    ) {}
}
