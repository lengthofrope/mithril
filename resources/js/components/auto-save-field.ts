import { apiClient } from '../utils/api-client';
import { debounce } from '../utils/debounce';
import type { ApiError } from '../types/api';

/**
 * Configuration for the autoSaveField Alpine component.
 */
interface AutoSaveFieldConfig {
    endpoint: string;
    field: string;
    debounceMs?: number;
}

/**
 * Save status indicator for a single field.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

const DEFAULT_DEBOUNCE_MS = 500;

/**
 * Alpine.js component that auto-saves a single field via PATCH on change.
 * Exposes `value`, `status`, `init()`, and `save()` to the template.
 */
function autoSaveField(config: AutoSaveFieldConfig): Record<string, unknown> {
    const debounceMs = config.debounceMs ?? DEFAULT_DEBOUNCE_MS;

    return {
        value: '' as string,
        status: 'idle' as SaveStatus,

        /**
         * Wires the debounced save watcher after Alpine initialises the component.
         */
        init(this: { value: string; status: SaveStatus; save: () => Promise<void>; $watch: (key: string, cb: () => void) => void }): void {
            const debouncedSave = debounce(() => {
                void this.save();
            }, debounceMs);

            this.$watch('value', debouncedSave);
        },

        /**
         * Sends a PATCH request with the current field value to the configured endpoint.
         */
        async save(this: { value: string; status: SaveStatus }): Promise<void> {
            this.status = 'saving';

            try {
                await apiClient.patch(config.endpoint, { [config.field]: this.value });
                this.status = 'saved';
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[autoSaveField] Save failed:', apiError.message);
                this.status = 'error';
            }
        },
    };
}

export { autoSaveField };
export type { AutoSaveFieldConfig, SaveStatus };
