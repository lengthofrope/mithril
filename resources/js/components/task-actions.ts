/**
 * Configuration for the taskActions Alpine component.
 */
interface TaskActionsConfig {
    createFollowUpUrl: string;
    convertToFollowUpUrl: string;
    deleteUrl: string;
    redirectUrl: string;
}

/**
 * Shape of the response data from follow-up creation/conversion endpoints.
 */
interface FollowUpResponse {
    follow_up_url?: string;
}

/**
 * Alpine.js component that handles the three task-level actions on the task
 * show page: creating a follow-up, converting to a follow-up, and deleting
 * the task. Uses web routes with CSRF tokens (not the API client).
 */
function taskActions(config: TaskActionsConfig): Record<string, unknown> {
    return {
        isCreating: false as boolean,
        isConverting: false as boolean,
        isDeleting: false as boolean,
        convertOpen: false as boolean,
        deleteOpen: false as boolean,

        /**
         * Creates a new follow-up from the current task and redirects to it.
         */
        async createFollowUp(this: TaskActionsContext): Promise<void> {
            if (this.isCreating) return;
            this.isCreating = true;

            try {
                const json = await sendWebRequest(config.createFollowUpUrl, 'POST');
                if (json?.success && json.data?.follow_up_url) {
                    window.location.href = json.data.follow_up_url;
                }
            } finally {
                this.isCreating = false;
            }
        },

        /**
         * Converts the current task to a follow-up after confirmation and
         * redirects to the new follow-up.
         */
        async doConvert(this: TaskActionsContext): Promise<void> {
            if (this.isConverting) return;
            this.isConverting = true;
            this.convertOpen = false;

            try {
                const json = await sendWebRequest(config.convertToFollowUpUrl, 'POST');
                if (json?.success && json.data?.follow_up_url) {
                    window.location.href = json.data.follow_up_url;
                }
            } finally {
                this.isConverting = false;
            }
        },

        /**
         * Deletes the current task after confirmation and redirects to the
         * task list or kanban view.
         */
        async doDelete(this: TaskActionsContext): Promise<void> {
            if (this.isDeleting) return;
            this.isDeleting = true;
            this.deleteOpen = false;

            try {
                const response = await sendWebRequest(config.deleteUrl, 'DELETE');
                if (response) {
                    window.location.href = config.redirectUrl;
                }
            } finally {
                this.isDeleting = false;
            }
        },
    };
}

/**
 * Shape of the task actions Alpine component context.
 */
interface TaskActionsContext {
    isCreating: boolean;
    isConverting: boolean;
    isDeleting: boolean;
    convertOpen: boolean;
    deleteOpen: boolean;
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
async function sendWebRequest(
    url: string,
    method: 'POST' | 'DELETE',
): Promise<{ success: boolean; data: FollowUpResponse } | null> {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': readCsrfToken(),
        },
        credentials: 'same-origin',
        body: method === 'POST' ? JSON.stringify({}) : undefined,
    });

    if (!response.ok) {
        return null;
    }

    if (method === 'DELETE') {
        return { success: true, data: {} };
    }

    return await response.json() as { success: boolean; data: FollowUpResponse };
}

export { taskActions };
export type { TaskActionsConfig };
