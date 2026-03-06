import { apiClient } from '../utils/api-client';
import { debounce } from '../utils/debounce';
import type { ApiError } from '../types/api';

/**
 * Configuration for the autoSaveForm Alpine component.
 */
interface AutoSaveFormConfig {
    endpoint: string;
    fields: string[];
    debounceMs?: number;
}

/**
 * Save status indicator for a form.
 */
type FormSaveStatus = 'idle' | 'saving' | 'saved' | 'error';

const DEFAULT_DEBOUNCE_MS = 500;

/**
 * Alpine.js component that auto-saves all changed form fields via PATCH.
 * Only the fields that have changed since the last save are sent.
 */
function autoSaveForm(config: AutoSaveFormConfig): Record<string, unknown> {
    const debounceMs = config.debounceMs ?? DEFAULT_DEBOUNCE_MS;

    return {
        fields: {} as Record<string, unknown>,
        status: 'idle' as FormSaveStatus,

        /**
         * Initialises field state and attaches a debounced watcher to each field.
         */
        init(this: {
            fields: Record<string, unknown>;
            status: FormSaveStatus;
            save: () => Promise<void>;
            $watch: (key: string, cb: () => void) => void;
        }): void {
            for (const field of config.fields) {
                this.fields[field] = '';
            }

            const debouncedSave = debounce(() => {
                void this.save();
            }, debounceMs);

            this.$watch('fields', debouncedSave);
        },

        /**
         * Sends a PATCH request with all currently tracked field values.
         */
        async save(this: { fields: Record<string, unknown>; status: FormSaveStatus }): Promise<void> {
            this.status = 'saving';

            try {
                await apiClient.patch(config.endpoint, { ...this.fields });
                this.status = 'saved';
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[autoSaveForm] Save failed:', apiError.message);
                this.status = 'error';
            }
        },
    };
}

export { autoSaveForm };
export type { AutoSaveFormConfig, FormSaveStatus };
