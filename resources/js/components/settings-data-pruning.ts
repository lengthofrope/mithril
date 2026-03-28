import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the settingsDataPruning Alpine component.
 */
interface SettingsDataPruningConfig {
    endpoint: string;
    initialDays: string;
}

/**
 * Alpine.js component for configuring the data retention period.
 */
function settingsDataPruning(config: SettingsDataPruningConfig): Record<string, unknown> {
    return {
        days: config.initialDays,
        saving: false as boolean,
        saved: false as boolean,
        error: '' as string,

        /**
         * Saves the retention period to the server.
         */
        async save(this: Record<string, unknown>): Promise<void> {
            this.saving = true;
            this.saved = false;
            this.error = '';
            try {
                await apiClient.patch(config.endpoint, {
                    prune_after_days: parseInt(this.days as string) || 90,
                });
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } catch (err) {
                const apiError = err as ApiError;
                const pruneErrors = apiError.errors?.prune_after_days;
                this.error = pruneErrors?.[0] ?? 'Failed to save.';
            } finally {
                this.saving = false;
            }
        },
    };
}

export { settingsDataPruning };
export type { SettingsDataPruningConfig };
