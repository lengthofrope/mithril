<?php

declare(strict_types=1);

/**
 * Configuration for the meetings module: recording storage, transcription, diarization, and AI extraction.
 */
return [
    'recording' => [
        'enabled' => (bool) env('MEETING_RECORDING_ENABLED', true),
        'disk' => env('MEETING_RECORDING_DISK', 'local'),
        'max_upload_mb' => (int) env('MEETING_RECORDING_MAX_MB', 500),
        'allowed_mime_types' => [
            'audio/webm',
            'video/webm',
            'audio/mp3',
            'audio/mpeg',
            'audio/wav',
            'audio/x-wav',
            'audio/mp4',
            'audio/x-m4a',
            'audio/ogg',
        ],
        'auto_start_meeting' => (bool) env('MEETING_AUTO_START_ON_RECORD', true),
    ],

    'transcription' => [
        'enabled' => (bool) env('MEETING_TRANSCRIPTION_ENABLED', true),
        'auto_start' => (bool) env('MEETING_AUTO_TRANSCRIBE', true),
        'base_url' => env('UNIFIED_SPEECH_BASE_URL', 'http://localhost:8090'),
    ],

    'diarization' => [
        'enabled' => (bool) env('MEETING_DIARIZATION_ENABLED', false),
        'base_url' => env('UNIFIED_SPEECH_BASE_URL', 'http://localhost:8090'),
    ],

    'speech' => [
        'auth_token' => env('SPEECH_AUTH_TOKEN', ''),
    ],

    'extraction' => [
        // Provider, API key, and model are now configured globally in config/ai.php
        // (AI_PROVIDER, AI_API_KEY, AI_MODEL env vars).
    ],
];
