<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review status of an AI-extracted meeting item.
 */
enum ExtractionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Modified = 'modified';
}
