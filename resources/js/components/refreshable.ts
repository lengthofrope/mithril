import { debounce } from '../utils/debounce';

/**
 * Configuration for the refreshable Alpine component.
 */
interface RefreshableConfig {
    url: string;
    topics?: string[];
    lazy?: boolean;
    pollInterval?: number;
}

const DEFAULT_POLL_INTERVAL_MS = 15000;
const DATA_CHANGED_DEBOUNCE_MS = 300;

/**
 * Alpine.js component that lazily loads and periodically refreshes an HTML
 * partial from the server. Responds to `data-changed` window events (with
 * optional topic filtering) and pauses polling while the tab is hidden.
 *
 * Server responses are expected to be same-origin Blade partials, so setting
 * innerHTML from the response text is safe here — the content is not
 * user-supplied and follows the same pattern used by filterManager.
 */
function refreshable(config: RefreshableConfig): Record<string, unknown> {
    const pollIntervalMs = config.pollInterval ?? DEFAULT_POLL_INTERVAL_MS;

    let pollTimer: ReturnType<typeof setInterval> | undefined;
    let dataChangedHandler: ((event: Event) => void) | undefined;
    let visibilityHandler: (() => void) | undefined;

    return {
        isLoading: false,
        isRefreshing: false,
        lastETag: null as string | null,

        /**
         * Initialises lazy loading, polling, data-changed listener, and
         * visibility-change handling.
         */
        init(this: {
            isLoading: boolean;
            isRefreshing: boolean;
            lastETag: string | null;
            fetchContent: (initial?: boolean) => Promise<void>;
            $el: HTMLElement;
        }): void {
            if (config.lazy) {
                this.isLoading = true;
                void this.fetchContent(true);
            }

            pollTimer = setInterval(() => {
                void this.fetchContent();
            }, pollIntervalMs);

            const debouncedRefresh = debounce((event: Event) => {
                const customEvent = event as CustomEvent<{ topic?: string }>;
                const topic = customEvent.detail?.topic;

                if (topic && config.topics && config.topics.length > 0) {
                    if (!config.topics.includes(topic)) {
                        return;
                    }
                }

                void this.fetchContent();
            }, DATA_CHANGED_DEBOUNCE_MS);

            dataChangedHandler = debouncedRefresh;
            window.addEventListener('data-changed', dataChangedHandler);

            visibilityHandler = () => {
                if (document.hidden) {
                    clearInterval(pollTimer);
                    pollTimer = undefined;
                    return;
                }

                pollTimer = setInterval(() => {
                    void this.fetchContent();
                }, pollIntervalMs);

                void this.fetchContent();
            };

            document.addEventListener('visibilitychange', visibilityHandler);
        },

        /**
         * Fetches updated HTML from the server and swaps the content of the
         * `[data-refresh-target]` element. Skips the swap on a 304 response.
         * Uses ETag headers to avoid redundant content transfers.
         */
        async fetchContent(
            this: {
                isLoading: boolean;
                isRefreshing: boolean;
                lastETag: string | null;
                $el: HTMLElement;
            },
            initial = false,
        ): Promise<void> {
            if (!initial) {
                this.isRefreshing = true;
            }

            const headers: Record<string, string> = {
                Accept: 'text/html',
            };

            if (this.lastETag !== null) {
                headers['If-None-Match'] = this.lastETag;
            }

            try {
                const response = await fetch(config.url, {
                    headers,
                    credentials: 'same-origin',
                });

                if (response.status === 304) {
                    return;
                }

                const etag = response.headers.get('ETag');

                if (etag !== null) {
                    this.lastETag = etag;
                }

                const html = await response.text();
                const target = this.$el.querySelector<HTMLElement>('[data-refresh-target]');

                if (target !== null) {
                    target.innerHTML = html;
                }
            } finally {
                this.isLoading = false;
                this.isRefreshing = false;
            }
        },

        /**
         * Cleans up the polling interval and all event listeners.
         */
        destroy(): void {
            clearInterval(pollTimer);

            if (dataChangedHandler !== undefined) {
                window.removeEventListener('data-changed', dataChangedHandler);
            }

            if (visibilityHandler !== undefined) {
                document.removeEventListener('visibilitychange', visibilityHandler);
            }
        },
    };
}

export { refreshable };
export type { RefreshableConfig };
