<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Email importance levels as reported by Microsoft Graph API.
 */
enum EmailImportance: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
