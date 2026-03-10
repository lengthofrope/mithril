import flatpickr from 'flatpickr';
import type { Instance as FlatpickrInstance } from 'flatpickr/dist/types/instance';

/**
 * Configuration for the datePicker Alpine component.
 */
interface DatePickerConfig {
    dateFormat?: string;
}

const DEFAULT_DATE_FORMAT = 'Y-m-d';

/**
 * Alpine.js component that initialises Flatpickr on a referenced input element.
 * Dispatches native input events so Alpine x-model bindings pick up changes.
 */
function datePicker(config: DatePickerConfig = {}): Record<string, unknown> {
    return {
        _flatpickr: null as FlatpickrInstance | null,

        /**
         * Initialises Flatpickr on the input referenced via x-ref="input".
         */
        init(this: {
            _flatpickr: FlatpickrInstance | null;
            $refs: { input: HTMLInputElement };
        }): void {
            const inputEl = this.$refs.input;

            this._flatpickr = flatpickr(inputEl, {
                dateFormat: config.dateFormat ?? DEFAULT_DATE_FORMAT,
                allowInput: true,
                onChange(_selectedDates: Date[], dateStr: string): void {
                    inputEl.value = dateStr;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                },
            });
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

export { datePicker };
export type { DatePickerConfig };
