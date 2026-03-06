import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the privacyToggle Alpine component.
 */
interface PrivacyToggleConfig {
    isPrivate: boolean;
    endpoint?: string;
}

/**
 * Alpine.js component that manages a privacy mode toggle.
 * Reflects its state via `isPrivate` and persists changes to the backend
 * when an endpoint is provided.
 */
function privacyToggle(config: PrivacyToggleConfig): Record<string, unknown> {
    return {
        isPrivate: config.isPrivate,
        isSaving: false,
        hasError: false,

        /**
         * Toggles the privacy state and optionally persists the change via PATCH.
         */
        async toggle(this: { isPrivate: boolean; isSaving: boolean; hasError: boolean }): Promise<void> {
            this.isPrivate = !this.isPrivate;

            if (config.endpoint === undefined) {
                return;
            }

            this.isSaving = true;
            this.hasError = false;

            try {
                await apiClient.patch(config.endpoint, { is_private: this.isPrivate });
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[privacyToggle] Toggle failed:', apiError.message);
                this.isPrivate = !this.isPrivate;
                this.hasError = true;
            } finally {
                this.isSaving = false;
            }
        },

        /**
         * Returns true when private content should be visible.
         */
        canViewPrivate(this: { isPrivate: boolean }): boolean {
            return !this.isPrivate;
        },
    };
}

export { privacyToggle };
export type { PrivacyToggleConfig };
