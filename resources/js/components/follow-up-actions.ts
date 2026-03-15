import { apiClient } from '../utils/api-client';

/**
 * Configuration for the followUpActions Alpine component.
 */
interface FollowUpActionsConfig {
    id: number;
    doneUrl: string;
    snoozeUrl: string;
}

/**
 * Alpine.js component that handles follow-up card actions (done, snooze)
 * via AJAX instead of form submissions. Dispatches a `data-changed` event
 * with the `follow_ups` topic so that refreshable components pick up the
 * change without a full page reload.
 */
function followUpActions(config: FollowUpActionsConfig): Record<string, unknown> {
    return {
        isProcessing: false as boolean,
        snoozeOpen: false as boolean,

        /**
         * Marks the follow-up as done via AJAX PATCH.
         */
        async markDone(this: { isProcessing: boolean; $el: HTMLElement }): Promise<void> {
            if (this.isProcessing) return;
            this.isProcessing = true;

            try {
                await sendAction(config.doneUrl, 'PATCH');
                apiClient.dispatchDataChanged('follow_ups');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Snoozes the follow-up by the given number of days via AJAX PATCH.
         */
        async snooze(this: { isProcessing: boolean; snoozeOpen: boolean }, days: number): Promise<void> {
            if (this.isProcessing) return;
            this.isProcessing = true;
            this.snoozeOpen = false;

            try {
                await sendAction(config.snoozeUrl, 'PATCH', { days });
                apiClient.dispatchDataChanged('follow_ups');
            } finally {
                this.isProcessing = false;
            }
        },

    };
}

/**
 * Reads the CSRF token from the meta tag injected by Laravel's Blade layout.
 */
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Sends an AJAX request to a web route with the proper headers so Laravel
 * recognises it as an AJAX call and returns JSON instead of a redirect.
 */
async function sendAction(url: string, method: 'PATCH' | 'POST', body?: Record<string, unknown>): Promise<void> {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': readCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });

    if (!response.ok) {
        console.error('[followUpActions] Action failed:', response.status);
    }
}

export { followUpActions };
export type { FollowUpActionsConfig };
