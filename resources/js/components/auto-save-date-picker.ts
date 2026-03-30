import flatpickr from 'flatpickr';
import type { Instance as FlatpickrInstance } from 'flatpickr/dist/types/instance';
import { apiClient } from '../utils/api-client';
import { debounce } from '../utils/debounce';
import type { ApiError } from '../types/api';

/**
 * Configuration for the autoSaveDatePicker Alpine component.
 */
interface AutoSaveDatePickerConfig {
    endpoint: string;
    field: string;
    dateFormat?: string;
    debounceMs?: number;
}

/**
 * Save status indicator for the date picker field.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

const DEFAULT_DATE_FORMAT = 'Y-m-d';
const DEFAULT_DEBOUNCE_MS = 500;

/**
 * Alpine.js component that combines Flatpickr date picking with auto-save
 * via PATCH on change. Solves the init() collision that occurs when using
 * Object.assign(autoSaveField(), datePicker()) by unifying both behaviours
 * in a single init() method.
 *
 * Exposes `value`, `status`, `init()`, `save()`, and `destroy()` to the template.
 */
function autoSaveDatePicker(config: AutoSaveDatePickerConfig): Record<string, unknown> {
    const debounceMs = config.debounceMs ?? DEFAULT_DEBOUNCE_MS;
    const dateFormat = config.dateFormat ?? DEFAULT_DATE_FORMAT;

    return {
        value: '' as string,
        status: 'idle' as SaveStatus,
        _initialized: false as boolean,
        _flatpickr: null as FlatpickrInstance | null,

        /**
         * Initialises Flatpickr on the referenced input and wires the
         * debounced auto-save watcher in a single init() call.
         */
        init(this: {
            value: string;
            status: SaveStatus;
            _initialized: boolean;
            _flatpickr: FlatpickrInstance | null;
            save: () => Promise<void>;
            $watch: (key: string, cb: () => void) => void;
            $nextTick: (cb: () => void) => void;
            $refs: { input: HTMLInputElement };
        }): void {
            const self = this;

            this._flatpickr = flatpickr(this.$refs.input, {
                dateFormat,
                allowInput: true,
                onChange(_selectedDates: Date[], dateStr: string): void {
                    self.value = dateStr;
                },
            });

            const debouncedSave = debounce(() => {
                void this.save();
            }, debounceMs);

            this.$watch('value', () => {
                if (!this._initialized) {
                    return;
                }
                debouncedSave();
            });

            this.$nextTick(() => {
                this._initialized = true;
            });
        },

        /**
         * Sends a PATCH request with the current date value to the configured endpoint.
         */
        async save(this: { value: string; status: SaveStatus }): Promise<void> {
            this.status = 'saving';

            try {
                await apiClient.patch(config.endpoint, { [config.field]: this.value });
                this.status = 'saved';
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[autoSaveDatePicker] Save failed:', apiError.message);
                this.status = 'error';
            }
        },

        /**
         * Cleans up the Flatpickr instance when the Alpine component is destroyed.
         */
        destroy(this: { _flatpickr: FlatpickrInstance | null }): void {
            if (this._flatpickr) {
                this._flatpickr.destroy();
                this._flatpickr = null;
            }
        },
    };
}

export { autoSaveDatePicker };
export type { AutoSaveDatePickerConfig, SaveStatus };
