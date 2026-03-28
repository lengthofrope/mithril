import { apiClient } from '../utils/api-client';

/**
 * Configuration for the meetingStatusTransition Alpine component.
 */
interface MeetingStatusTransitionConfig {
    endpoint: string;
    initialStatus: string;
}

/**
 * Alpine.js component for transitioning a meeting between statuses.
 * Reloads the page on successful transition.
 */
function meetingStatusTransition(config: MeetingStatusTransitionConfig): Record<string, unknown> {
    return {
        currentStatus: config.initialStatus,

        /**
         * Sends a PATCH request to transition the meeting to the given status.
         */
        async transition(this: Record<string, unknown>, status: string): Promise<void> {
            try {
                await apiClient.patch(config.endpoint, { status });
                this.currentStatus = status;
                window.location.reload();
            } catch {
                console.error('[meetingStatusTransition] Transition failed');
            }
        },
    };
}

export { meetingStatusTransition };
export type { MeetingStatusTransitionConfig };
