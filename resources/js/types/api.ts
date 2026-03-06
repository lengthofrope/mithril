/**
 * Standard API response envelope returned by all backend endpoints.
 */
interface ApiResponse<T> {
    success: true;
    data: T;
    message?: string;
    saved_at?: string;
}

/**
 * Error response returned by the backend when validation or processing fails.
 */
interface ApiError {
    success: false;
    errors: Record<string, string[]>;
    message: string;
}

/**
 * Payload shape for a single item in a reorder request.
 */
interface ReorderItem {
    id: number;
    sort_order: number;
}

/**
 * Payload shape for moving an item between groups (e.g. task groups or kanban columns).
 */
interface MoveItem {
    id: number;
    from_group: number | null;
    to_group: number | null;
    sort_order: number;
}

export type { ApiResponse, ApiError, ReorderItem, MoveItem };
