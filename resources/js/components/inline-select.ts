import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the inlineSelect Alpine component.
 */
interface InlineSelectConfig {
    endpoint: string;
    field: string;
    value: string;
    options: Record<string, string>;
    colorMap: Record<string, string>;
}

/**
 * Save status indicator for the inline select.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Alpine.js component that renders a clickable pill with a dropdown to change
 * a field value inline. Sends a PATCH request on selection.
 */
function inlineSelect(config: InlineSelectConfig): Record<string, unknown> {
    return {
        isOpen: false as boolean,
        value: config.value,
        status: 'idle' as SaveStatus,

        /**
         * Returns the display label for the current value.
         */
        get label(): string {
            return config.options[this.value as string] ?? '';
        },

        /**
         * Returns the CSS color classes for the current value.
         */
        get colorClass(): string {
            return config.colorMap[this.value as string] ?? '';
        },

        /**
         * Toggles the dropdown open/closed.
         */
        toggle(): void {
            this.isOpen = !this.isOpen;
        },

        /**
         * Closes the dropdown.
         */
        close(): void {
            this.isOpen = false;
        },

        /**
         * Selects a new value, closes the dropdown, and saves via PATCH.
         */
        async select(this: { value: string; isOpen: boolean; status: SaveStatus }, newValue: string): Promise<void> {
            if (newValue === this.value) {
                this.isOpen = false;
                return;
            }

            this.value = newValue;
            this.isOpen = false;
            this.status = 'saving';

            try {
                await apiClient.patch(config.endpoint, { [config.field]: newValue });
                this.status = 'saved';
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[inlineSelect] Save failed:', apiError.message);
                this.status = 'error';
            }
        },
    };
}

export { inlineSelect };
export type { InlineSelectConfig };
