import { apiClient } from '../utils/api-client';
import { debounce } from '../utils/debounce';
import type { ApiError } from '../types/api';

/**
 * Configuration for the liveCounter Alpine component.
 */
interface LiveCounterConfig {
    endpoint: string;
    counterKey: string;
    initialValue: number;
    debounceMs?: number;
}

/**
 * Response shape for the counters endpoint.
 */
type CounterData = Record<string, number>;

const DEFAULT_DEBOUNCE_MS = 1000;

/**
 * Alpine.js component that displays a counter value and automatically
 * refreshes it when a `data-changed` event is dispatched on window.
 */
function liveCounter(config: LiveCounterConfig): Record<string, unknown> {
    const debounceMs = config.debounceMs ?? DEFAULT_DEBOUNCE_MS;

    return {
        count: config.initialValue,

        /**
         * Sets up a debounced listener for the `data-changed` window event.
         */
        init(this: { count: number; refresh: () => Promise<void> }): void {
            const debouncedRefresh = debounce(() => {
                void this.refresh();
            }, debounceMs);

            window.addEventListener('data-changed', debouncedRefresh);
        },

        /**
         * Fetches fresh counter data from the API and updates the displayed count.
         */
        async refresh(this: { count: number }): Promise<void> {
            try {
                const response = await apiClient.get<CounterData>(config.endpoint);
                const freshValue = response.data[config.counterKey];

                if (freshValue !== undefined) {
                    this.count = freshValue;
                }
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[liveCounter] Refresh failed:', apiError.message);
            }
        },
    };
}

export { liveCounter };
export type { LiveCounterConfig };
