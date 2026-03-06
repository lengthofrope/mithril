<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Team member availability statuses.
 */
enum MemberStatus: string
{
    case Available = 'available';
    case Absent = 'absent';
    case PartiallyAvailable = 'partially_available';
}
