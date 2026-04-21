import type { Options } from 'flatpickr/dist/types/options';

/**
 * Shared Flatpickr defaults applied to all date picker instances in the application.
 * Sets the week to start on Monday (ISO 8601) for European users.
 */
const DEFAULT_FLATPICKR_OPTIONS: Partial<Options> = {
    locale: {
        firstDayOfWeek: 1,
    },
};

export { DEFAULT_FLATPICKR_OPTIONS };
