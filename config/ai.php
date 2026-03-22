<?php

declare(strict_types=1);

/**
 * Global AI provider configuration.
 *
 * Used for meeting extraction and any future AI-powered features.
 * Speech transcription and diarization have their own config in config/meetings.php.
 */
return [
    'enabled' => (bool) env('AI_ENABLED', true),
    'provider' => env('AI_PROVIDER', 'openai'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
];
