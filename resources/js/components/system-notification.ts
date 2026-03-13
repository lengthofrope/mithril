/**
 * Get the CSRF token from the meta tag.
 */
function getCSRFToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Alpine.js component for dismissing a system notification.
 */
function systemNotification(notificationId: number): Record<string, unknown> {
    return {
        notificationId,
        isVisible: true,
        isDismissing: false,

        /**
         * Dismiss the notification for the current user via API.
         */
        async dismiss(this: { notificationId: number; isVisible: boolean; isDismissing: boolean }): Promise<void> {
            if (this.isDismissing) return;
            this.isDismissing = true;

            try {
                const response = await fetch(`/api/v1/system-notifications/${this.notificationId}/dismiss`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as { success: boolean };

                if (json.success) {
                    this.isVisible = false;
                }
            } finally {
                this.isDismissing = false;
            }
        },
    };
}

export { systemNotification };
