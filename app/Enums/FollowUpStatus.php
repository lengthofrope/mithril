<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Follow-up lifecycle statuses.
 */
enum FollowUpStatus: string
{
    case Open = 'open';
    case Snoozed = 'snoozed';
    case Done = 'done';
}
