/**
 * Configuration for the meetingTabs Alpine component.
 */
interface MeetingTabsConfig {
    availableTabs: string[];
}

/**
 * Alpine.js component for tab navigation on the meeting detail page.
 * Reads and writes the active tab via URL query parameters.
 */
function meetingTabs(config: MeetingTabsConfig): Record<string, unknown> {
    return {
        availableTabs: config.availableTabs,
        activeTab: null as string | null,

        /**
         * Reads the active tab from the URL on initialisation, falling back to the first tab.
         */
        init(this: Record<string, unknown>): void {
            const requested = new URLSearchParams(window.location.search).get('tab') ?? 'prep';
            const tabs = this.availableTabs as string[];
            this.activeTab = tabs.includes(requested) ? requested : 'prep';
        },

        /**
         * Sets the active tab by updating the URL and navigating.
         */
        setTab(tab: string): void {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.location.href = url.toString();
        },
    };
}

export { meetingTabs };
export type { MeetingTabsConfig };
