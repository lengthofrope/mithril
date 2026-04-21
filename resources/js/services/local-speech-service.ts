import type {
    TranscribeResponse,
    DiarizeResponse,
    SpeechServiceHealthResponse,
    SpeechStreamEvent,
} from '@/types/speech-service';

/**
 * Error class for speech service communication failures.
 */
class SpeechServiceError extends Error {
    constructor(
        message: string,
        public readonly statusCode: number | null = null
    ) {
        super(message);
        this.name = 'SpeechServiceError';
    }
}

/**
 * Build request headers with optional authentication token.
 */
function buildHeaders(token: string | null): Record<string, string> {
    const headers: Record<string, string> = {};

    if (token) {
        headers['X-Speech-Token'] = token;
    }

    return headers;
}

/**
 * Build multipart form data with audio file and language parameter.
 */
function buildFormData(audioBlob: Blob, language: string): FormData {
    const formData = new FormData();
    formData.append('file', audioBlob, 'recording.webm');
    formData.append('language', language);

    return formData;
}

/**
 * Handle fetch response errors with descriptive messages.
 */
async function handleResponse<T>(response: Response, url: string): Promise<T> {
    if (response.status === 401) {
        throw new SpeechServiceError(
            `Authentication failed (401). Check your speech service token.`,
            401
        );
    }

    if (response.status === 503) {
        throw new SpeechServiceError(
            `Speech service not ready (503). The service at ${url} is still loading.`,
            503
        );
    }

    if (!response.ok) {
        throw new SpeechServiceError(
            `Speech service returned an error (${response.status}).`,
            response.status
        );
    }

    return response.json() as Promise<T>;
}

/**
 * Send audio to the local speech service for transcription.
 */
async function transcribe(
    audioBlob: Blob,
    language: string,
    url: string,
    token: string | null
): Promise<TranscribeResponse> {
    const endpoint = `${url.replace(/\/+$/, '')}/transcribe`;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: buildHeaders(token),
            body: buildFormData(audioBlob, language),
        });

        return handleResponse<TranscribeResponse>(response, url);
    } catch (error) {
        if (error instanceof SpeechServiceError) {
            throw error;
        }

        throw new SpeechServiceError(
            `Could not connect to your speech service at ${url}. Is it running?`
        );
    }
}

/**
 * Send audio to the local speech service for diarization.
 */
async function diarize(
    audioBlob: Blob,
    language: string,
    url: string,
    token: string | null
): Promise<DiarizeResponse> {
    const endpoint = `${url.replace(/\/+$/, '')}/diarize`;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: buildHeaders(token),
            body: buildFormData(audioBlob, language),
        });

        return handleResponse<DiarizeResponse>(response, url);
    } catch (error) {
        if (error instanceof SpeechServiceError) {
            throw error;
        }

        throw new SpeechServiceError(
            `Could not connect to your speech service at ${url}. Is it running?`
        );
    }
}

/**
 * Check the health status of a local speech service instance.
 */
async function health(
    url: string,
    token: string | null
): Promise<SpeechServiceHealthResponse> {
    const endpoint = `${url.replace(/\/+$/, '')}/health`;

    try {
        const response = await fetch(endpoint, {
            method: 'GET',
            headers: buildHeaders(token),
        });

        return handleResponse<SpeechServiceHealthResponse>(response, url);
    } catch (error) {
        if (error instanceof SpeechServiceError) {
            throw error;
        }

        throw new SpeechServiceError(
            `Could not connect to your speech service at ${url}. Is it running?`
        );
    }
}

/**
 * Represents a single parsed SSE frame with its event name and raw data string.
 */
interface SseFrame {
    event: string;
    data: string;
}

/**
 * Parse a ReadableStream of UTF-8 bytes into an async generator of SSE frames.
 *
 * The SSE protocol uses blank lines to delimit events. Each event accumulates
 * `event:` and `data:` field lines; multi-line data fields are joined with
 * newlines per the spec. Fields other than `event` and `data` are ignored.
 */
async function* parseSseStream(
    stream: ReadableStream<Uint8Array>
): AsyncGenerator<SseFrame> {
    const reader = stream.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let currentEvent = 'message';
    let dataLines: string[] = [];

    try {
        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                buffer += decoder.decode();
            } else {
                buffer += decoder.decode(value, { stream: true });
            }

            const lines = buffer.split(/\r\n|\r|\n/);

            buffer = done ? '' : (lines.pop() ?? '');

            for (const line of lines) {
                if (line === '') {
                    if (dataLines.length > 0) {
                        yield { event: currentEvent, data: dataLines.join('\n') };
                    }

                    currentEvent = 'message';
                    dataLines = [];
                    continue;
                }

                if (line.startsWith('event:')) {
                    currentEvent = line.slice(6).trim();
                } else if (line.startsWith('data:')) {
                    dataLines.push(line.slice(5).trim());
                }
            }

            if (done) {
                if (dataLines.length > 0) {
                    yield { event: currentEvent, data: dataLines.join('\n') };
                }
                break;
            }
        }
    } finally {
        try {
            await reader.cancel();
        } catch {
            /* already cancelled */
        }
        reader.releaseLock();
    }
}

/**
 * Map an SSE frame from the speech service into a typed SpeechStreamEvent.
 *
 * Returns null for unrecognised event names so callers can skip them safely.
 */
function mapSseFrame(frame: SseFrame): SpeechStreamEvent | null {
    const payload = JSON.parse(frame.data) as Record<string, unknown>;

    switch (frame.event) {
        case 'progress':
            if (typeof payload['stage'] !== 'string' || typeof payload['progress'] !== 'number') {
                return null;
            }
            return {
                type: 'progress',
                stage: payload['stage'],
                progress: payload['progress'],
                elapsed_s: typeof payload['elapsed_s'] === 'number' ? payload['elapsed_s'] : 0,
            };

        case 'stage':
            if (typeof payload['stage'] !== 'string') {
                return null;
            }
            return {
                type: 'stage',
                stage: payload['stage'],
            };

        case 'result':
            return {
                type: 'result',
                data: payload as unknown as TranscribeResponse | DiarizeResponse,
            };

        case 'error':
            if (typeof payload['detail'] !== 'string') {
                return null;
            }
            return {
                type: 'error',
                detail: payload['detail'],
            };

        default:
            return null;
    }
}

/**
 * Open a streaming POST request and yield typed SSE events until the result
 * arrives or an error terminates the stream.
 *
 * HTTP-level errors (non-2xx) are thrown as SpeechServiceError before any
 * events are yielded. Mid-stream `error` events are thrown as SpeechServiceError
 * with statusCode null. Network failures are wrapped the same way.
 *
 * Malformed SSE frames (invalid JSON payloads) are skipped silently; this means
 * a malformed `error` frame would not surface as an exception to the caller.
 */
async function* consumeStream(
    endpoint: string,
    formData: FormData,
    token: string | null,
    url: string
): AsyncGenerator<SpeechStreamEvent> {
    let response: Response;

    try {
        response = await fetch(endpoint, {
            method: 'POST',
            headers: buildHeaders(token),
            body: formData,
        });
    } catch {
        throw new SpeechServiceError(
            `Could not connect to your speech service at ${url}. Is it running?`
        );
    }

    if (response.status === 401) {
        await response.body?.cancel();
        throw new SpeechServiceError(
            `Authentication failed (401). Check your speech service token.`,
            401
        );
    }

    if (response.status === 503) {
        await response.body?.cancel();
        throw new SpeechServiceError(
            `Speech service not ready (503). The service at ${url} is still loading.`,
            503
        );
    }

    if (!response.ok) {
        await response.body?.cancel();
        throw new SpeechServiceError(
            `Speech service returned an error (${response.status}).`,
            response.status
        );
    }

    if (!response.body) {
        throw new SpeechServiceError(
            `Speech service returned an empty response body.`
        );
    }

    for await (const frame of parseSseStream(response.body)) {
        let event: SpeechStreamEvent | null;

        try {
            event = mapSseFrame(frame);
        } catch {
            continue;
        }

        if (event === null) {
            continue;
        }

        if (event.type === 'error') {
            throw new SpeechServiceError(event.detail);
        }

        yield event;

        if (event.type === 'result') {
            return;
        }
    }
}

/**
 * Send audio to the local speech service transcription streaming endpoint.
 *
 * Yields progress and stage events as they arrive, then a single result event.
 * Errors at the HTTP layer or mid-stream are thrown as SpeechServiceError.
 */
async function* transcribeStream(
    audioBlob: Blob,
    language: string,
    url: string,
    token: string | null
): AsyncGenerator<SpeechStreamEvent> {
    const endpoint = `${url.replace(/\/+$/, '')}/transcribe/stream`;

    yield* consumeStream(endpoint, buildFormData(audioBlob, language), token, url);
}

/**
 * Send audio to the local speech service diarization streaming endpoint.
 *
 * Yields stage and progress events during processing, then a single result
 * event carrying the full diarized payload.
 * Errors at the HTTP layer or mid-stream are thrown as SpeechServiceError.
 */
async function* diarizeStream(
    audioBlob: Blob,
    language: string,
    url: string,
    token: string | null
): AsyncGenerator<SpeechStreamEvent> {
    const endpoint = `${url.replace(/\/+$/, '')}/diarize/stream`;

    yield* consumeStream(endpoint, buildFormData(audioBlob, language), token, url);
}

/**
 * Return true when the health response indicates the speech service supports
 * SSE streaming endpoints.
 */
function supportsStreaming(health: SpeechServiceHealthResponse | null | undefined): boolean {
    return health?.streaming === true;
}

export {
    transcribe,
    diarize,
    health,
    transcribeStream,
    diarizeStream,
    supportsStreaming,
    parseSseStream,
    mapSseFrame,
    consumeStream,
    SpeechServiceError,
};
