import type { JiraIssueLink } from '../types/models';

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

const LINK_TYPE_MAP: Record<string, LinkTypeInfo> = {
    'App\\Models\\Bila': { label: 'Bila', badge: 'B', urlPrefix: '/bilas/' },
    'App\\Models\\Task': { label: 'Task', badge: 'T', urlPrefix: '/tasks/' },
    'App\\Models\\FollowUp': { label: 'Follow-up', badge: 'F', urlPrefix: '/follow-ups/' },
    'App\\Models\\Note': { label: 'Note', badge: 'N', urlPrefix: '/notes/' },
};

/**
 * Map a JiraIssueLink to a display-friendly badge object.
 */
function mapLinkToBadge(link: JiraIssueLink): LinkBadge {
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
 * Alpine.js component for managing Jira issue actions (create resources, view links).
 */
function jiraActions(issueId: number, initialLinks: JiraIssueLink[] = [], canCreateBila: boolean = false): Record<string, unknown> {
    return {
        issueId,
        links: initialLinks.map(mapLinkToBadge),
        canCreateBila,
        menuOpen: false,
        isLoading: false,
        errorMessage: '',

        /**
         * Create a resource of the given type from this Jira issue.
         */
        async createResource(this: { issueId: number; links: LinkBadge[]; menuOpen: boolean; isLoading: boolean; errorMessage: string }, type: string): Promise<void> {
            if (this.isLoading) return;
            this.isLoading = true;
            this.menuOpen = false;
            this.errorMessage = '';

            try {
                const response = await fetch(`/api/v1/jira-issues/${this.issueId}/create/${type}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as { success: boolean; data?: { link?: JiraIssueLink }; message?: string };

                if (json.success && json.data?.link) {
                    const badge = mapLinkToBadge(json.data.link);
                    this.links.push(badge);
                    window.location.href = badge.url;
                    return;
                } else if (!json.success && json.message) {
                    this.errorMessage = json.message;
                }
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Remove a link between this Jira issue and a resource (does not delete the resource).
         */
        async unlinkResource(this: { issueId: number; links: LinkBadge[]; isLoading: boolean }, linkId: number): Promise<void> {
            if (this.isLoading) return;
            this.isLoading = true;

            try {
                const response = await fetch(`/api/v1/jira-issues/${this.issueId}/links/${linkId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json',
                    },
                });

                const json = await response.json() as { success: boolean };

                if (json.success) {
                    this.links = this.links.filter((link: LinkBadge) => link.id !== linkId);
                }
            } finally {
                this.isLoading = false;
            }
        },
    };
}

export { jiraActions };
export type { LinkBadge };
