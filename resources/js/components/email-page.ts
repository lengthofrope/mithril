import type { Email } from '../types/models';

/**
 * Standard API response shape.
 */
interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
}

/**
 * A group of emails sharing the same Outlook category.
 */
interface CategoryGroup {
    name: string;
    emails: Email[];
}

/**
 * Get the CSRF token from the meta tag.
 */
function getCSRFToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Group emails by their Outlook categories, sorted by received_at within each group.
 *
 * Emails with multiple categories appear in each matching group.
 * Emails without categories are placed in an "Uncategorized" group.
 */
function groupByCategory(emails: Email[]): CategoryGroup[] {
    const groups: Record<string, Email[]> = {};

    for (const email of emails) {
        const categories = (email.categories ?? []) as string[];

        if (categories.length === 0) {
            groups['Uncategorized'] ??= [];
            groups['Uncategorized'].push(email);
        } else {
            for (const cat of categories) {
                groups[cat] ??= [];
                groups[cat].push(email);
            }
        }
    }

    return Object.entries(groups)
        .map(([name, groupEmails]) => ({
            name,
            emails: groupEmails.sort((a, b) =>
                new Date(b.received_at).getTime() - new Date(a.received_at).getTime()
            ),
        }))
        .sort((a, b) => {
            if (a.name === 'Uncategorized') return 1;
            if (b.name === 'Uncategorized') return -1;
            return a.name.localeCompare(b.name);
        });
}

/**
 * Alpine.js component for the mail page — lists, filters, and dismisses synced emails.
 */
function emailPage(): Record<string, unknown> {
    return {
        emails: [] as Email[],
        sourceFilter: 'all',
        isLoading: true,
        errorMessage: '',

        /**
         * Whether the current view shows emails grouped by Outlook category.
         */
        get showCategoryGroups(): boolean {
            return (this as unknown as { sourceFilter: string }).sourceFilter === 'categorized';
        },

        /**
         * Emails grouped by Outlook category, for the categorized view.
         */
        get categoryGroups(): CategoryGroup[] {
            return groupByCategory((this as unknown as { emails: Email[] }).emails);
        },

        /**
         * Fetch emails on component init and listen for dismiss events from child components.
         */
        async init(this: { emails: Email[]; sourceFilter: string; isLoading: boolean; errorMessage: string; fetchEmails: () => Promise<void>; dismissEmail: (emailId: number) => Promise<void>; $el: HTMLElement }): Promise<void> {
            this.$el.addEventListener('dismiss-email', ((event: CustomEvent<{ emailId: number }>) => {
                this.dismissEmail(event.detail.emailId);
            }) as EventListener);

            await this.fetchEmails();
        },

        /**
         * Fetch emails from the API with the current source filter.
         */
        async fetchEmails(this: { emails: Email[]; sourceFilter: string; isLoading: boolean; errorMessage: string }): Promise<void> {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const params = new URLSearchParams();
                if (this.sourceFilter !== 'all') {
                    params.set('source', this.sourceFilter);
                }

                const response = await fetch(`/api/v1/emails?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });

                const json = await response.json() as ApiResponse<Email[]>;

                if (json.success && json.data) {
                    this.emails = json.data;
                } else {
                    this.errorMessage = json.message ?? 'Failed to load emails.';
                }
            } catch {
                this.errorMessage = 'Failed to load emails.';
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Change the source filter and reload emails.
         */
        async setFilter(this: { sourceFilter: string; fetchEmails: () => Promise<void> }, source: string): Promise<void> {
            this.sourceFilter = source;
            await this.fetchEmails();
        },

        /**
         * Dismiss an email (hide from the list).
         */
        async dismissEmail(this: { emails: Email[] }, emailId: number): Promise<void> {
            try {
                const response = await fetch(`/api/v1/emails/${emailId}/dismiss`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as ApiResponse;

                if (json.success) {
                    this.emails = this.emails.filter((e: Email) => e.id !== emailId);
                }
            } catch {
                // Silently fail — email stays in list
            }
        },
    };
}

export { emailPage };
export type { CategoryGroup };
