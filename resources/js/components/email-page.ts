import type { Email, EmailLink } from '../types/models';

/**
 * Data shape for a linked resource badge displayed in the UI.
 */
interface LinkBadge {
    id: number;
    type: string;
    label: string;
    url: string;
}

/**
 * Lookup map entry for a linkable model type.
 */
interface LinkTypeInfo {
    label: string;
    badge: string;
    urlPrefix: string;
}

/**
 * Standard API response shape.
 */
interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
}

const LINK_TYPE_MAP: Record<string, LinkTypeInfo> = {
    'App\\Models\\Bila': { label: 'Bila', badge: 'B', urlPrefix: '/bilas/' },
    'App\\Models\\Task': { label: 'Task', badge: 'T', urlPrefix: '/tasks/' },
    'App\\Models\\FollowUp': { label: 'Follow-up', badge: 'F', urlPrefix: '/follow-ups/' },
    'App\\Models\\Note': { label: 'Note', badge: 'N', urlPrefix: '/notes/' },
};

/**
 * Map an EmailLink to a display-friendly badge object.
 */
function mapLinkToBadge(link: EmailLink): LinkBadge {
    const info = LINK_TYPE_MAP[link.linkable_type] ?? { label: 'Unknown', badge: '?', urlPrefix: '#' };

    return {
        id: link.id,
        type: info.badge,
        label: info.label,
        url: info.urlPrefix !== '#' ? `${info.urlPrefix}${link.linkable_id}` : '#',
    };
}

/**
 * Get the CSRF token from the meta tag.
 */
function getCSRFToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Extended email with UI-specific properties.
 */
interface EmailWithUI extends Email {
    _links: LinkBadge[];
    _menuOpen: boolean;
    _isLoading: boolean;
}

/**
 * Alpine.js component for the mail page — lists, filters, and acts on synced emails.
 */
function emailPage(): Record<string, unknown> {
    return {
        emails: [] as EmailWithUI[],
        sourceFilter: 'all',
        isLoading: true,
        errorMessage: '',

        /**
         * Fetch emails on component init.
         */
        async init(this: { emails: EmailWithUI[]; sourceFilter: string; isLoading: boolean; errorMessage: string; fetchEmails: () => Promise<void> }): Promise<void> {
            await this.fetchEmails();
        },

        /**
         * Fetch emails from the API with the current source filter.
         */
        async fetchEmails(this: { emails: EmailWithUI[]; sourceFilter: string; isLoading: boolean; errorMessage: string }): Promise<void> {
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
                    this.emails = json.data.map((email: Email): EmailWithUI => ({
                        ...email,
                        _links: (email.links ?? []).map(mapLinkToBadge),
                        _menuOpen: false,
                        _isLoading: false,
                    }));
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
         * Create a resource from an email.
         */
        async createResource(this: { emails: EmailWithUI[] }, emailId: number, type: string): Promise<void> {
            const email = this.emails.find((e: EmailWithUI) => e.id === emailId);
            if (!email || email._isLoading) return;

            email._isLoading = true;
            email._menuOpen = false;

            try {
                const response = await fetch(`/api/v1/emails/${emailId}/create/${type}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as ApiResponse<{ link?: EmailLink }>;

                if (json.success && json.data?.link) {
                    const badge = mapLinkToBadge(json.data.link);
                    email._links.push(badge);
                }
            } finally {
                email._isLoading = false;
            }
        },

        /**
         * Dismiss an email (hide from the list).
         */
        async dismissEmail(this: { emails: EmailWithUI[] }, emailId: number): Promise<void> {
            const email = this.emails.find((e: EmailWithUI) => e.id === emailId);
            if (!email || email._isLoading) return;

            email._isLoading = true;

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
                    this.emails = this.emails.filter((e: EmailWithUI) => e.id !== emailId);
                }
            } finally {
                if (email) email._isLoading = false;
            }
        },

        /**
         * Remove a link between an email and a resource.
         */
        async unlinkResource(this: { emails: EmailWithUI[] }, emailId: number, linkId: number): Promise<void> {
            const email = this.emails.find((e: EmailWithUI) => e.id === emailId);
            if (!email || email._isLoading) return;

            email._isLoading = true;

            try {
                const response = await fetch(`/api/v1/emails/${emailId}/links/${linkId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as ApiResponse;

                if (json.success) {
                    email._links = email._links.filter((link: LinkBadge) => link.id !== linkId);
                }
            } finally {
                email._isLoading = false;
            }
        },
    };
}

export { emailPage };
export type { LinkBadge, EmailWithUI };
