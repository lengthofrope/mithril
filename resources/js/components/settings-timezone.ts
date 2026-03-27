import { apiClient } from '../utils/api-client';

/**
 * Configuration for the settingsTimezone Alpine component.
 */
interface SettingsTimezoneConfig {
    endpoint: string;
    initialTimezone: string;
}

/**
 * Alpine.js component for the timezone setting.
 * Saves the selected timezone via PATCH on change.
 */
function settingsTimezone(config: SettingsTimezoneConfig): Record<string, unknown> {
    return {
        timezone: config.initialTimezone,
        saving: false as boolean,
        saved: false as boolean,

        /**
         * Saves the selected timezone to the server.
         */
        async save(this: Record<string, unknown>): Promise<void> {
            this.saving = true;
            this.saved = false;
            try {
                await apiClient.patch(config.endpoint, { timezone: this.timezone as string });
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } finally {
                this.saving = false;
            }
        },
    };
}

export { settingsTimezone };
export type { SettingsTimezoneConfig };
