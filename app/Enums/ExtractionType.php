<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of items that can be extracted from a meeting transcription.
 */
enum ExtractionType: string
{
    case Task = 'task';
    case FollowUp = 'follow_up';
    case Agreement = 'agreement';
    case Decision = 'decision';
}
