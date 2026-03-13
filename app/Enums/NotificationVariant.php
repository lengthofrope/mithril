<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Visual variant for system notifications.
 */
enum NotificationVariant: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Success = 'success';
    case Error = 'error';
}
