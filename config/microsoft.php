<?php

declare(strict_types=1);

return [
    'client_id'     => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'tenant_id'     => env('MICROSOFT_TENANT_ID'),
    'redirect_uri'  => env('MICROSOFT_REDIRECT_URI'),

    'scopes' => [
        'User.Read',
        'Calendars.Read',
        'offline_access',
    ],

    'authority' => 'https://login.microsoftonline.com/',
    'graph_url'  => 'https://graph.microsoft.com/v1.0/',

    'calendar_sync_interval_minutes'     => 15,
    'availability_sync_interval_minutes' => 5,
    'calendar_days_ahead'                => 7,
    'schedule_batch_size'                => 20,
];
