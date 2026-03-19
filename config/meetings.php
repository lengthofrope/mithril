<?php

declare(strict_types=1);

/**
 * Configuration for the meetings module: recording storage, transcription, and AI extraction.
 */
return [
    'recording' => [
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

    'extraction' => [
        'provider' => env('MEETING_EXTRACTION_PROVIDER', 'openai'),

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('MEETING_EXTRACTION_MODEL', 'gpt-4o-mini'),
        ],
    ],
];
