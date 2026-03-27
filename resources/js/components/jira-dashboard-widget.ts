import { apiClient } from '../utils/api-client';

/**
 * Configuration for the jiraDashboardWidget Alpine component.
 */
interface JiraDashboardWidgetConfig {
    limit: number;
}

/**
 * Shape of a single Jira issue returned by the dashboard API.
 */
interface JiraIssue {
    id: number;
    issue_key: string;
    summary: string;
    status_name: string;
    priority_name: string | null;
    web_url: string;
}

/**
 * Shape of the dashboard API response data.
 */
interface JiraDashboardData {
    issues: JiraIssue[];
    total: number;
}

/**
 * Alpine.js component that fetches and displays Jira dashboard issues.
 * Loads issues on initialization and exposes loading/error state.
 */
function jiraDashboardWidget(config: JiraDashboardWidgetConfig): Record<string, unknown> {
    return {
        issues: [] as JiraIssue[],
        total: 0 as number,
        isLoading: true as boolean,
        errorMessage: '' as string,
        limit: config.limit,

        /**
         * Fetches Jira issues on component initialization.
         */
        async init(this: JiraDashboardWidgetContext): Promise<void> {
            await this.fetchIssues();
        },

        /**
         * Fetches Jira dashboard issues from the API endpoint.
         */
        async fetchIssues(this: JiraDashboardWidgetContext): Promise<void> {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const response = await apiClient.get<JiraDashboardData>(
                    `/api/v1/jira-issues/dashboard?limit=${this.limit}`
                );

                if (response.success && response.data) {
                    this.issues = response.data.issues;
                    this.total = response.data.total;
                } else {
                    this.errorMessage = response.message ?? 'Failed to load Jira issues.';
                }
            } catch {
                this.errorMessage = 'Failed to load Jira issues.';
            } finally {
                this.isLoading = false;
            }
        },
    };
}

/**
 * Shape of the Jira dashboard widget Alpine component context.
 */
interface JiraDashboardWidgetContext {
    issues: JiraIssue[];
    total: number;
    isLoading: boolean;
    errorMessage: string;
    limit: number;
    fetchIssues: () => Promise<void>;
}

export { jiraDashboardWidget };
export type { JiraDashboardWidgetConfig };
