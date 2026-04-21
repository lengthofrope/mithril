import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the activitySortToggle Alpine component.
 */
interface ActivitySortToggleConfig {
    userId: number;
    initialSortOrder: string;
}

/**
 * Alpine.js component that toggles the activity feed sort order between
 * ascending (oldest first) and descending (newest first), persists the
 * preference via the AutoSave endpoint, and triggers a feed refresh.
 */
function activitySortToggle(config: ActivitySortToggleConfig): Record<string, unknown> {
    return {
        sortOrder: config.initialSortOrder as string,
        isSaving: false as boolean,

        /**
         * Returns true when the current sort order is descending.
         */
        get isDesc(): boolean {
            return (this as { sortOrder: string }).sortOrder === 'desc';
        },

        /**
         * Toggles between asc and desc, persists the preference, and
         * dispatches a data-changed event so the refreshable component
         * reloads the feed partial.
         */
        async toggle(this: {
            sortOrder: string;
            isSaving: boolean;
        }): Promise<void> {
            if (this.isSaving) {
                return;
            }

            const next = this.sortOrder === 'asc' ? 'desc' : 'asc';

            this.isSaving = true;

            try {
                await apiClient.post('/api/v1/auto-save', {
                    model: 'user',
                    id: config.userId,
                    field: 'activity_sort_order',
                    value: next,
                });

                this.sortOrder = next;
                apiClient.dispatchDataChanged('activities');
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[activitySortToggle] toggle failed:', apiError.message);
            } finally {
                this.isSaving = false;
            }
        },
    };
}

export { activitySortToggle };
export type { ActivitySortToggleConfig };
