<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Email source types for filtering which emails to sync from Outlook.
 */
enum EmailSource: string
{
    case Flagged = 'flagged';
    case Categorized = 'categorized';
    case Unread = 'unread';
}
