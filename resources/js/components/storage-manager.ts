import { apiClient } from '../utils/api-client';

/**
 * Alpine.js component for managing file attachments on the storage settings page.
 * Handles delete confirmation and executing the DELETE request via the API client.
 */
function storageManager(): Record<string, unknown> {
    return {
        deleting: null as number | null,
        confirmDeleteId: null as number | null,

        /**
         * Opens the delete confirmation for the given attachment ID.
         */
        confirmDelete(this: { confirmDeleteId: number | null }, id: number): void {
            this.confirmDeleteId = id;
        },

        /**
         * Cancels the pending delete confirmation.
         */
        cancelDelete(this: { confirmDeleteId: number | null }): void {
            this.confirmDeleteId = null;
        },

        /**
         * Executes the DELETE request for the confirmed attachment and reloads
         * the page on success.
         */
        async doDelete(this: { confirmDeleteId: number | null; deleting: number | null }): Promise<void> {
            const id = this.confirmDeleteId;
            if (!id || this.deleting) return;
            this.confirmDeleteId = null;
            this.deleting = id;

            try {
                await apiClient.delete(`/api/v1/attachments/${id}`);
                window.location.reload();
            } finally {
                this.deleting = null;
            }
        },
    };
}

export { storageManager };
