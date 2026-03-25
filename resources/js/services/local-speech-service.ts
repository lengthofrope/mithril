import type {
    TranscribeResponse,
    DiarizeResponse,
    SpeechServiceHealthResponse,
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

export { transcribe, diarize, health, SpeechServiceError };
