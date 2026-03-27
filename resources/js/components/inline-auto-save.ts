import { apiClient } from '../utils/api-client';
import { debounce } from '../utils/debounce';
import type { ApiError } from '../types/api';

/**
 * Configuration for the inlineAutoSave Alpine component.
 */
interface InlineAutoSaveConfig {
    model: string;
    id: number;
    field: string;
    debounceMs?: number;
}

/**
 * Save status indicator for inline auto-save fields.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

const DEFAULT_DEBOUNCE_MS = 500;
const AUTO_SAVE_ENDPOINT = '/api/v1/auto-save';
const SAVED_DISPLAY_MS = 2000;

/**
 * Status text mapping for display in the template.
 */
const STATUS_TEXT_MAP: Record<SaveStatus, string> = {
    idle: '',
    saving: 'Saving...',
    saved: 'Saved',
    error: 'Error',
};

/**
 * Alpine.js component for inline auto-saving via the generic auto-save endpoint.
 * Posts { model, id, field, value } to /api/v1/auto-save on value change.
 * Exposes `value`, `status`, `statusText`, `init()`, and `save()` to the template.
 */
function inlineAutoSave(config: InlineAutoSaveConfig): Record<string, unknown> {
    const debounceMs = config.debounceMs ?? DEFAULT_DEBOUNCE_MS;

    return {
        value: '' as string,
        status: 'idle' as SaveStatus,
        statusText: '' as string,
        _initialized: false as boolean,
        _savedTimer: undefined as ReturnType<typeof setTimeout> | undefined,

        /**
         * Wires the debounced save watcher after Alpine initialises the component.
         */
        init(this: {
            value: string;
            status: SaveStatus;
            statusText: string;
            _initialized: boolean;
            _savedTimer: ReturnType<typeof setTimeout> | undefined;
            save: () => Promise<void>;
            $watch: (key: string, cb: () => void) => void;
            $nextTick: (cb: () => void) => void;
        }): void {
            const debouncedSave = debounce(() => {
                void this.save();
            }, debounceMs);

            this.$watch('value', () => {
                if (!this._initialized) {
                    return;
                }
                debouncedSave();
            });

            this.$watch('status', () => {
                this.statusText = STATUS_TEXT_MAP[this.status];
            });

            this.$nextTick(() => {
                this._initialized = true;
            });
        },

        /**
         * Sends a POST request to the auto-save endpoint with the current field value.
         */
        async save(this: {
            value: string;
            status: SaveStatus;
            _savedTimer: ReturnType<typeof setTimeout> | undefined;
        }): Promise<void> {
            this.status = 'saving';

            try {
                await apiClient.post(AUTO_SAVE_ENDPOINT, {
                    model: config.model,
                    id: config.id,
                    field: config.field,
                    value: this.value,
                });
                this.status = 'saved';

                clearTimeout(this._savedTimer);
                this._savedTimer = setTimeout(() => {
                    if (this.status === 'saved') {
                        this.status = 'idle';
                    }
                }, SAVED_DISPLAY_MS);
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[inlineAutoSave] Save failed:', apiError.message);
                this.status = 'error';
            }
        },
    };
}

export { inlineAutoSave };
export type { InlineAutoSaveConfig, SaveStatus };
