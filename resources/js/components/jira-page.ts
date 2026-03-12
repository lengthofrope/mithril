/**
 * CSRF token getter for API requests.
 */
function getCSRFToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Alpine.js component for the Jira issues browse page.
 *
 * Handles dismiss/undismiss actions via API calls and updates the DOM.
 */
function jiraPage(config: { dismissEndpoint: string }): Record<string, unknown> {
    return {
        dismissEndpoint: config.dismissEndpoint,

        /**
         * Dismiss a Jira issue via API.
         */
        async dismiss(this: { dismissEndpoint: string }, issueId: number): Promise<void> {
            const response = await fetch(`${this.dismissEndpoint}/${issueId}/dismiss`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCSRFToken(),
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                window.location.reload();
            }
        },

        /**
         * Restore a dismissed Jira issue via API.
         */
        async undismiss(this: { dismissEndpoint: string }, issueId: number): Promise<void> {
            const response = await fetch(`${this.dismissEndpoint}/${issueId}/undismiss`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCSRFToken(),
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                window.location.reload();
            }
        },
    };
}

export { jiraPage };
