<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of meetings.
 */
enum MeetingType: string
{
    case Team = 'team';
    case OneOnOne = 'one_on_one';
    case Other = 'other';
}
