/**
 * Configuration for the followUpPage Alpine component.
 */
interface FollowUpPageConfig {
    convertUrl: string;
}

/**
 * Shape of the response data from the follow-up convert endpoint.
 */
interface ConvertResponse {
    task_url?: string;
}

/**
 * Alpine.js component for the follow-up show page that handles converting
 * a follow-up back into a task. Uses a web route with CSRF token.
 */
function followUpPage(config: FollowUpPageConfig): Record<string, unknown> {
    return {
        isOpen: false as boolean,
        isProcessing: false as boolean,

        /**
         * Sends the convert-to-task request and redirects to the new task page.
         */
        async doConvert(this: FollowUpPageContext): Promise<void> {
            if (this.isProcessing) return;
            this.isProcessing = true;
            this.isOpen = false;

            try {
                const response = await fetch(config.convertUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({}),
                });

                const json = await response.json() as { success: boolean; data: ConvertResponse };
                if (json.success && json.data?.task_url) {
                    window.location.href = json.data.task_url;
                }
            } finally {
                this.isProcessing = false;
            }
        },
    };
}

/**
 * Shape of the follow-up page Alpine component context.
 */
interface FollowUpPageContext {
    isOpen: boolean;
    isProcessing: boolean;
}

/**
 * Reads the CSRF token from the meta tag injected by Laravel's Blade layout.
 */
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export { followUpPage };
export type { FollowUpPageConfig };
