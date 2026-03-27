import { apiClient } from '../utils/api-client';

/**
 * Configuration for the recordingDelete Alpine component.
 */
interface RecordingDeleteConfig {
    endpoint: string;
}

/**
 * Alpine.js component for deleting a meeting recording via the API.
 * Shows a confirmation dialog before performing the DELETE request.
 */
function recordingDelete(config: RecordingDeleteConfig): Record<string, unknown> {
    return {
        /**
         * Confirms and deletes the recording, then reloads the page on success.
         */
        async deleteRecording(): Promise<void> {
            if (!confirm('Delete this recording?')) {
                return;
            }

            await apiClient.delete(config.endpoint);
            window.location.reload();
        },
    };
}

export { recordingDelete };
export type { RecordingDeleteConfig };
