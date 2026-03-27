import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the settingsDashboardWidgets Alpine component.
 */
interface SettingsDashboardWidgetsConfig {
    endpoint: string;
    tasks: string;
    followUps: string;
    meetings: string;
}

/**
 * Alpine.js component for configuring the number of upcoming items shown on dashboard widgets.
 */
function settingsDashboardWidgets(config: SettingsDashboardWidgetsConfig): Record<string, unknown> {
    return {
        tasks: config.tasks,
        followUps: config.followUps,
        meetings: config.meetings,
        saving: false as boolean,
        saved: false as boolean,
        error: '' as string,

        /**
         * Saves the dashboard widget configuration to the server.
         */
        async save(this: Record<string, unknown>): Promise<void> {
            this.saving = true;
            this.saved = false;
            this.error = '';
            try {
                await apiClient.patch(config.endpoint, {
                    dashboard_upcoming_tasks: (this.tasks as string) === '' ? null : parseInt(this.tasks as string),
                    dashboard_upcoming_follow_ups: (this.followUps as string) === '' ? null : parseInt(this.followUps as string),
                    dashboard_upcoming_meetings: (this.meetings as string) === '' ? null : parseInt(this.meetings as string),
                });
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } catch (err) {
                const apiError = err as ApiError;
                const errors = apiError.errors ?? {};
                const firstError = Object.values(errors).flat()[0];
                this.error = firstError ?? 'Failed to save.';
            } finally {
                this.saving = false;
            }
        },
    };
}

export { settingsDashboardWidgets };
export type { SettingsDashboardWidgetsConfig };
