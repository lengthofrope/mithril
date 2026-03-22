<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a meeting through its lifecycle.
 */
enum MeetingStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
