<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Team member availability statuses.
 */
enum MemberStatus: string
{
    case Available = 'available';
    case PartiallyAvailable = 'partially_available';
    case WorkingElsewhere = 'working_elsewhere';
    case InAMeeting = 'in_a_meeting';
    case Absent = 'absent';
}
