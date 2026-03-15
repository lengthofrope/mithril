<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of activities in the activity feed.
 */
enum ActivityType: string
{
    case Comment = 'comment';
    case Attachment = 'attachment';
    case Link = 'link';
    case System = 'system';
}
