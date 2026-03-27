import { autoSaveField } from './auto-save-field';

/**
 * Configuration for the meetingTypeChanger Alpine component.
 */
interface MeetingTypeChangerConfig {
    endpoint: string;
    initialValue: string;
    attendeeCount: number;
}

/**
 * Alpine.js component that extends autoSaveField with meeting type change logic.
 * Warns when switching to one_on_one with multiple attendees.
 */
function meetingTypeChanger(config: MeetingTypeChangerConfig): Record<string, unknown> {
    const base = autoSaveField({ endpoint: config.endpoint, field: 'type' });

    return {
        ...base,
        showOneOnOneWarning: false as boolean,
        pendingValue: null as string | null,
        attendeeCount: config.attendeeCount,

        /**
         * Initialises the component by setting the initial value and wiring up the base init.
         */
        init(this: Record<string, unknown>): void {
            this.value = config.initialValue;
            (base.init as (this: Record<string, unknown>) => void).call(this);
        },

        /**
         * Handles a type change, showing a warning if switching to one_on_one with multiple attendees.
         */
        handleTypeChange(this: Record<string, unknown>, newValue: string): void {
            if (newValue === 'one_on_one' && (this.attendeeCount as number) > 1) {
                this.pendingValue = newValue;
                this.showOneOnOneWarning = true;
                const nextTick = this.$nextTick as (cb: () => void) => void;
                nextTick(() => {
                    const typeSelect = (this.$refs as Record<string, HTMLSelectElement>).typeSelect;
                    typeSelect.value = this.value as string;
                });
                return;
            }
            this.value = newValue;
            const dispatch = this.$dispatch as (event: string, detail: Record<string, unknown>) => void;
            dispatch('meeting-type-changed', { value: newValue });
        },

        /**
         * Confirms the pending type change, clears attendees, and dispatches events.
         */
        confirmTypeChange(this: Record<string, unknown>): void {
            this.showOneOnOneWarning = false;
            this.value = this.pendingValue;
            this.pendingValue = null;
            const nextTick = this.$nextTick as (cb: () => void) => void;
            nextTick(() => {
                const typeSelect = (this.$refs as Record<string, HTMLSelectElement>).typeSelect;
                typeSelect.value = this.value as string;
            });
            const dispatch = this.$dispatch as (event: string, detail?: Record<string, unknown>) => void;
            dispatch('meeting-type-changed', { value: this.value as string });
            dispatch('meeting-clear-attendees');
        },

        /**
         * Cancels the pending type change and hides the warning.
         */
        cancelTypeChange(this: Record<string, unknown>): void {
            this.showOneOnOneWarning = false;
            this.pendingValue = null;
        },
    };
}

export { meetingTypeChanger };
export type { MeetingTypeChangerConfig };
