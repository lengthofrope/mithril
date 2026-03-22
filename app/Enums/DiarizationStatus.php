<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of speaker diarization through its lifecycle.
 */
enum DiarizationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
