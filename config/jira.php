<?php

declare(strict_types=1);

return [
    'client_id'     => env('JIRA_CLIENT_ID'),
    'client_secret' => env('JIRA_CLIENT_SECRET'),
    'redirect_uri'  => env('JIRA_REDIRECT_URI'),

    'scopes' => [
        'read:jira-work',
        'read:jira-user',
        'offline_access',
    ],

    'auth_url'      => 'https://auth.atlassian.com/authorize',
    'token_url'     => 'https://auth.atlassian.com/oauth/token',
    'resources_url' => 'https://api.atlassian.com/oauth/token/accessible-resources',
    'api_base_url'  => 'https://api.atlassian.com/ex/jira/',

    'sync_interval_minutes' => 5,
    'max_issues_per_sync'   => 250,
];
