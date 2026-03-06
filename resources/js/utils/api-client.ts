import type { ApiResponse, ApiError } from '../types/api';

type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'DELETE';

/**
 * Reads the CSRF token from the meta tag injected by Laravel's Blade layout.
 */
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Refreshes the CSRF token by fetching a dedicated sanctum endpoint.
 */
async function refreshCsrfToken(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
}

/**
 * Executes a fetch request and returns a typed ApiResponse.
 * Throws an ApiError-shaped object on non-2xx responses.
 */
async function executeRequest<T>(
    method: HttpMethod,
    url: string,
    body?: Record<string, unknown>,
    isRetry = false,
): Promise<ApiResponse<T>> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': readCsrfToken(),
    };

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (response.status === 419 && !isRetry) {
        await refreshCsrfToken();
        return executeRequest<T>(method, url, body, true);
    }

    const json: unknown = await response.json();

    if (!response.ok) {
        throw json as ApiError;
    }

    return json as ApiResponse<T>;
}

/**
 * Type-safe HTTP client for communicating with Laravel API endpoints.
 * Handles CSRF tokens, JSON serialization, and automatic 419 retry.
 */
class ApiClient {
    /**
     * Sends a GET request to the given URL.
     */
    public async get<T>(url: string): Promise<ApiResponse<T>> {
        return executeRequest<T>('GET', url);
    }

    /**
     * Sends a POST request with a JSON body.
     */
    public async post<T>(url: string, body: Record<string, unknown>): Promise<ApiResponse<T>> {
        return executeRequest<T>('POST', url, body);
    }

    /**
     * Sends a PATCH request with a JSON body.
     */
    public async patch<T>(url: string, body: Record<string, unknown>): Promise<ApiResponse<T>> {
        return executeRequest<T>('PATCH', url, body);
    }

    /**
     * Sends a DELETE request.
     */
    public async delete<T>(url: string): Promise<ApiResponse<T>> {
        return executeRequest<T>('DELETE', url);
    }
}

const apiClient = new ApiClient();

export { apiClient, ApiClient };
