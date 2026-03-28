import { apiClient } from '../utils/api-client';

/**
 * Health status data from the speech service endpoint.
 */
interface SystemHealthData {
    ready: boolean;
    device?: string;
}

/**
 * Alpine.js component for checking the system speech service health status.
 */
function settingsSystemHealth(): Record<string, unknown> {
    return {
        systemHealth: null as SystemHealthData | null,
        loading: true as boolean,

        /**
         * Fetches the system health status on initialisation.
         */
        async init(this: Record<string, unknown>): Promise<void> {
            try {
                const response = await apiClient.get<SystemHealthData>('/api/v1/speech-service/health');
                this.systemHealth = response.data;
            } finally {
                this.loading = false;
            }
        },
    };
}

export { settingsSystemHealth };
export type { SystemHealthData };
