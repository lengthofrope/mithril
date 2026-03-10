<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origin source of a team member's availability status.
 */
enum StatusSource: string
{
    case Manual    = 'manual';
    case Microsoft = 'microsoft';
}
