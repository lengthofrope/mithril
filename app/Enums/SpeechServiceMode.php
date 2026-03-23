<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Processing mode for speech service transcription.
 */
enum SpeechServiceMode: string
{
    case Server = 'server';
    case Local = 'local';
}
