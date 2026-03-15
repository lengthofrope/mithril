<?php

declare(strict_types=1);

/**
 * Configuration for user file attachment storage limits.
 */
return [
    'max_storage_mb' => (int) env('ATTACHMENT_MAX_STORAGE_MB', 1024),
];
