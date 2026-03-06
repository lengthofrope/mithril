import type { ApiError } from '../types/api';

/**
 * Supported filter input types.
 */
type FilterType = 'select' | 'multi-select' | 'date-range' | 'boolean' | 'search';

/**
 * Definition of a single filter field.
 */
interface FilterDef {
    field: string;
    type: FilterType;
    label: string;
    options?: { value: string; label: string }[];
}

/**
 * Active filter state — values keyed by field name.
 */
type FilterState = Record<string, string | string[] | boolean | null>;

/**
 * Configuration for the filterManager Alpine component.
 */
interface FilterManagerConfig {
    endpoint: string;
    resultsSelector: string;
    filters: FilterDef[];
}

/**
 * Serialises the filter state to a URL query string.
 */
function buildQueryString(state: FilterState): string {
    const params = new URLSearchParams();

    for (const [field, value] of Object.entries(state)) {
        if (value === null || value === '') {
            continue;
        }

        if (Array.isArray(value)) {
            for (const v of value) {
                params.append(`${field}[]`, v);
            }
        } else if (typeof value === 'boolean') {
            params.set(field, value ? '1' : '0');
        } else {
            params.set(field, value);
        }
    }

    return params.toString();
}

/**
 * Alpine.js component providing generic filter and search functionality.
 * Sends filters as query parameters and replaces a DOM container with
 * the server-returned HTML partial.
 */
function filterManager(config: FilterManagerConfig): Record<string, unknown> {
    const initialState: FilterState = {};

    for (const filterDef of config.filters) {
        if (filterDef.type === 'multi-select') {
            initialState[filterDef.field] = [];
        } else if (filterDef.type === 'boolean') {
            initialState[filterDef.field] = null;
        } else {
            initialState[filterDef.field] = '';
        }
    }

    return {
        filterState: { ...initialState } as FilterState,
        isLoading: false,
        hasError: false,

        /**
         * Initialises the component and runs the initial fetch on mount.
         */
        init(this: { filterState: FilterState; isLoading: boolean; hasError: boolean; applyFilters: () => Promise<void>; $watch: (key: string, cb: () => void) => void }): void {
            this.$watch('filterState', () => {
                void this.applyFilters();
            });
        },

        /**
         * Fetches filtered results from the server and replaces the results container HTML.
         */
        async applyFilters(this: { filterState: FilterState; isLoading: boolean; hasError: boolean }): Promise<void> {
            const resultsContainer = document.querySelector<HTMLElement>(config.resultsSelector);

            if (resultsContainer === null) {
                console.warn('[filterManager] Results container not found:', config.resultsSelector);
                return;
            }

            const query = buildQueryString(this.filterState);
            const url = query.length > 0 ? `${config.endpoint}?${query}` : config.endpoint;

            this.isLoading = true;
            this.hasError = false;

            try {
                const response = await fetch(url, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Unexpected response status: ${response.status}`);
                }

                const html = await response.text();
                resultsContainer.innerHTML = html;
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[filterManager] Filter request failed:', apiError.message);
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Resets all filter fields to their initial empty state and re-fetches.
         */
        async resetFilters(this: { filterState: FilterState; applyFilters: () => Promise<void> }): Promise<void> {
            this.filterState = { ...initialState };
            await this.applyFilters();
        },
    };
}

export { filterManager };
export type { FilterManagerConfig, FilterDef, FilterType, FilterState };
