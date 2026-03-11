/**
 * Alpine.js data component for managing task recurrence settings.
 *
 * Controls the visibility of interval and custom days fields
 * based on the recurrence toggle state.
 */
function recurrenceSettings(): Record<string, unknown> {
    return {
        isRecurring: false as boolean,
        interval: null as string | null,
        customDays: null as number | null,

        /**
         * Initialize the component with the task's current recurrence state.
         */
        init(): void {
            // Values are initialized via x-init in the Blade template
        },

        /**
         * Whether the interval selector should be visible.
         */
        showIntervalSelector(this: { isRecurring: boolean }): boolean {
            return this.isRecurring;
        },

        /**
         * Whether the custom days input should be visible.
         */
        showCustomDays(this: { isRecurring: boolean; interval: string | null }): boolean {
            return this.isRecurring && this.interval === 'custom';
        },
    };
}

export { recurrenceSettings };
