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
 * Pagination metadata returned by paginated API endpoints.
 */
interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * API response shape for paginated endpoints.
 */
interface PaginatedApiResponse<T = unknown> extends ApiResponse<T> {
    meta?: PaginationMeta;
}

/**
 * A group of emails sharing the same Outlook category.
 */
interface CategoryGroup {
    name: string;
    emails: Email[];
}

/**
 * A group of emails sharing the same date label (Today, Yesterday, etc.).
 */
interface DateGroup {
    label: string;
    emails: Email[];
    defaultOpen: boolean;
}

/**
 * Determine the date label for a given date string relative to today.
 */
function getDateLabel(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();

    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfYesterday = new Date(startOfToday);
    startOfYesterday.setDate(startOfYesterday.getDate() - 1);

    const startOfWeek = new Date(startOfToday);
    startOfWeek.setDate(startOfWeek.getDate() - startOfToday.getDay() + (startOfToday.getDay() === 0 ? -6 : 1));

    const emailDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());

    if (emailDate >= startOfToday) {
        return 'Today';
    }
    if (emailDate >= startOfYesterday) {
        return 'Yesterday';
    }
    if (emailDate >= startOfWeek) {
        return 'This week';
    }
    return 'Older';
}

/**
 * Group emails by date label, preserving received_at order within each group.
 *
 * When the total email count is below the collapse threshold, all groups
 * default to open since collapsing adds no value for small inboxes.
 */
function groupByDate(emails: Email[]): DateGroup[] {
    const order = ['Today', 'Yesterday', 'This week', 'Older'];
    const collapseThreshold = 25;
    const groups: Record<string, Email[]> = {};

    for (const email of emails) {
        const label = getDateLabel(email.received_at);
        groups[label] ??= [];
        groups[label].push(email);
    }

    let cumulative = 0;

    return order
        .filter((label) => groups[label]?.length)
        .map((label) => {
            cumulative += groups[label].length;

            return {
                label,
                emails: groups[label],
                defaultOpen: cumulative < collapseThreshold,
            };
        });
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
 * Alpine.js component for the mail page — lists and filters synced emails.
 */
function emailPage(): Record<string, unknown> {
    return {
        emails: [] as Email[],
        sourceFilter: 'all',
        isLoading: true,
        errorMessage: '',
        currentPage: 1,
        lastPage: 1,
        total: 0,
        perPage: 25,

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
         * Emails grouped by date label (Today, Yesterday, This week, Older).
         */
        get dateGroups(): DateGroup[] {
            return groupByDate((this as unknown as { emails: Email[] }).emails);
        },

        /**
         * Whether pagination controls should be displayed.
         */
        get hasPagination(): boolean {
            return (this as unknown as { lastPage: number }).lastPage > 1;
        },

        /**
         * Whether the current page is the first page.
         */
        get isFirstPage(): boolean {
            return (this as unknown as { currentPage: number }).currentPage === 1;
        },

        /**
         * Whether the current page is the last page.
         */
        get isLastPage(): boolean {
            const self = this as unknown as { currentPage: number; lastPage: number };
            return self.currentPage === self.lastPage;
        },

        /**
         * Fetch emails on component init.
         */
        async init(this: { emails: Email[]; isLoading: boolean; fetchEmails: () => Promise<void> }): Promise<void> {
            await this.fetchEmails();
        },

        /**
         * Fetch emails from the API with the current source filter and pagination.
         */
        async fetchEmails(this: { emails: Email[]; sourceFilter: string; isLoading: boolean; errorMessage: string; currentPage: number; lastPage: number; total: number; perPage: number }): Promise<void> {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const params = new URLSearchParams();
                if (this.sourceFilter !== 'all') {
                    params.set('source', this.sourceFilter);
                }
                params.set('page', String(this.currentPage));
                params.set('per_page', String(this.perPage));

                const response = await fetch(`/api/v1/emails?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });

                const json = await response.json() as PaginatedApiResponse<Email[]>;

                if (json.success && json.data) {
                    this.emails = json.data;

                    if (json.meta) {
                        this.currentPage = json.meta.current_page;
                        this.lastPage = json.meta.last_page;
                        this.total = json.meta.total;
                        this.perPage = json.meta.per_page;
                    }
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
         * Change the source filter and reload emails from page 1.
         */
        async setFilter(this: { sourceFilter: string; currentPage: number; fetchEmails: () => Promise<void> }, source: string): Promise<void> {
            this.sourceFilter = source;
            this.currentPage = 1;
            await this.fetchEmails();
        },

        /**
         * Navigate to a specific page and fetch emails.
         */
        async goToPage(this: { currentPage: number; lastPage: number; fetchEmails: () => Promise<void> }, page: number): Promise<void> {
            if (page < 1 || page > this.lastPage) {
                return;
            }
            this.currentPage = page;
            await this.fetchEmails();
        },

        /**
         * Navigate to the next page.
         */
        async nextPage(this: { currentPage: number; lastPage: number; goToPage: (page: number) => Promise<void> }): Promise<void> {
            await this.goToPage(this.currentPage + 1);
        },

        /**
         * Navigate to the previous page.
         */
        async previousPage(this: { currentPage: number; goToPage: (page: number) => Promise<void> }): Promise<void> {
            await this.goToPage(this.currentPage - 1);
        },

    };
}

export { emailPage };
export type { CategoryGroup, DateGroup };
