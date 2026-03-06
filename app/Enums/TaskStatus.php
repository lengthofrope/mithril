<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task lifecycle statuses.
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Done = 'done';
}
