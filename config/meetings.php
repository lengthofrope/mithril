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
        'provider' => env('MEETING_TRANSCRIPTION_PROVIDER', 'whisper_cpp'),
        'auto_start' => (bool) env('MEETING_AUTO_TRANSCRIBE', true),

        'whisper_cpp' => [
            'base_url' => env('WHISPER_CPP_BASE_URL', 'http://localhost:8080'),
        ],

        'whisper' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('WHISPER_MODEL', 'whisper-1'),
        ],
    ],

    'diarization' => [
        'enabled' => (bool) env('MEETING_DIARIZATION_ENABLED', false),

        'pyannote' => [
            'base_url' => env('PYANNOTE_BASE_URL', 'http://localhost:8081'),
        ],
    ],

    'extraction' => [
        // Provider, API key, and model are now configured globally in config/ai.php
        // (AI_PROVIDER, AI_API_KEY, AI_MODEL env vars).
    ],
];
