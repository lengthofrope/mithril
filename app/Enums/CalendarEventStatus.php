<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Availability status of a calendar event as reported by Microsoft Graph.
 */
enum CalendarEventStatus: string
{
    case Free             = 'free';
    case Tentative        = 'tentative';
    case Busy             = 'busy';
    case OutOfOffice      = 'oof';
    case WorkingElsewhere = 'workingElsewhere';
}
