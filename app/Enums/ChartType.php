<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supported chart visualisation types for analytics widgets.
 */
enum ChartType: string
{
    case Donut = 'donut';
    case Bar = 'bar';
    case BarHorizontal = 'bar_horizontal';
    case StackedBar = 'stacked_bar';
    case Line = 'line';
}
