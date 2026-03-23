/**
 * Diarized speech segment with speaker identification and timing.
 */
interface DiarizedSegment {
    speaker: string;
    start: number;
    end: number;
    text: string;
}

/**
 * Response from the speech service /transcribe endpoint.
 */
interface TranscribeResponse {
    text: string;
}

/**
 * Response from the speech service /diarize endpoint.
 */
interface DiarizeResponse {
    segments: DiarizedSegment[];
    speakers: string[];
}

/**
 * Response from the speech service /health endpoint.
 */
interface SpeechServiceHealthResponse {
    ready: boolean;
    device: string;
    models: Record<string, unknown>;
}

/**
 * Processing mode for speech service.
 */
type SpeechServiceMode = 'server' | 'local';

export type {
    DiarizedSegment,
    TranscribeResponse,
    DiarizeResponse,
    SpeechServiceHealthResponse,
    SpeechServiceMode,
};
