/**
 * Alpine.js component for manual sync trigger buttons.
 *
 * Fires a POST request to the given endpoint, shows a spinning state,
 * then reloads the page once the sync job has been dispatched.
 */
function syncButton(endpoint: string): Record<string, unknown> {
    return {
        endpoint,
        syncing: false,

        /**
         * Trigger a sync and reload the page after a short delay
         * to allow the queued job to process.
         */
        async sync(this: { endpoint: string; syncing: boolean }): Promise<void> {
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

                if (response.ok) {
                    setTimeout(() => window.location.reload(), 3000);
                } else {
                    this.syncing = false;
                }
            } catch {
                this.syncing = false;
            }
        },
    };
}

export { syncButton };
