/**
 * Maximum number of poll attempts before giving up.
 */
const MAX_POLLS = 30;

/**
 * Milliseconds between each poll request.
 */
const POLL_INTERVAL = 2000;

interface SyncButtonState {
    endpoint: string;
    statusEndpoint: string;
    syncing: boolean;
    pollUntilChanged(originalSyncedAt: string | null): Promise<void>;
}

/**
 * Alpine.js component for manual sync trigger buttons.
 *
 * Fires a POST request to dispatch a sync job, then polls a status
 * endpoint until the synced_at timestamp changes, indicating the
 * job has completed. Reloads the page on completion.
 */
function syncButton(endpoint: string): Record<string, unknown> {
    const type = endpoint.split('/').pop() ?? '';
    const statusEndpoint = `/api/v1/sync/${type}/status`;

    return {
        endpoint,
        statusEndpoint,
        syncing: false,

        /**
         * Trigger a sync and poll until completion.
         */
        async sync(this: SyncButtonState): Promise<void> {
            if (this.syncing) {
                return;
            }

            this.syncing = true;

            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta?.getAttribute('content') ?? '';

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    this.syncing = false;
                    return;
                }

                const data = await response.json();
                const originalSyncedAt = data.synced_at ?? null;

                await this.pollUntilChanged(originalSyncedAt);
            } catch {
                this.syncing = false;
            }
        },

        /**
         * Poll the status endpoint until synced_at differs from the original value.
         */
        async pollUntilChanged(this: SyncButtonState, originalSyncedAt: string | null): Promise<void> {
            for (let i = 0; i < MAX_POLLS; i++) {
                await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL));

                try {
                    const response = await fetch(this.statusEndpoint, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!response.ok) {
                        continue;
                    }

                    const data = await response.json();

                    if (data.synced_at !== originalSyncedAt) {
                        window.location.reload();
                        return;
                    }
                } catch {
                    // Network error — keep polling.
                }
            }

            this.syncing = false;
        },
    };
}

export { syncButton };
