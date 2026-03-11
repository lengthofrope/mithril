<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Available recurrence intervals for recurring tasks.
 */
enum RecurrenceInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Custom = 'custom';
}
