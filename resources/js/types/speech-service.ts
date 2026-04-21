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
    streaming?: boolean;
}

/**
 * SSE progress event emitted during active transcription or the transcribing
 * stage of diarization; carries numeric 0-1 progress.
 */
interface SpeechProgressEvent {
    type: 'progress';
    stage: string;
    progress: number;
    elapsed_s?: number;
}

/**
 * SSE stage-change event indicating a new named processing phase has started.
 */
interface SpeechStageEvent {
    type: 'stage';
    stage: string;
}

/**
 * SSE result event carrying the final response payload once processing completes.
 */
interface SpeechResultEvent {
    type: 'result';
    data: TranscribeResponse | DiarizeResponse;
}

/**
 * SSE error event emitted when processing fails mid-stream.
 */
interface SpeechErrorEvent {
    type: 'error';
    detail: string;
}

/**
 * Union of all typed SSE events emitted by the streaming speech endpoints.
 */
type SpeechStreamEvent =
    | SpeechProgressEvent
    | SpeechStageEvent
    | SpeechResultEvent
    | SpeechErrorEvent;

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
    SpeechProgressEvent,
    SpeechStageEvent,
    SpeechResultEvent,
    SpeechErrorEvent,
    SpeechStreamEvent,
};
