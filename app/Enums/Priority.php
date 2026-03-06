<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task priority levels.
 */
enum Priority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';
}
