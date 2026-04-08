import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Shape of a single search result item returned by the API.
 */
interface SearchResultItem {
    id: number;
    title?: string;
    name?: string;
    description?: string;
    team_id?: number;
    team?: { id: number; name?: string } | null;
    [key: string]: unknown;
}

/**
 * Search results grouped by entity type from the API response.
 */
interface SearchResults {
    tasks: SearchResultItem[];
    notes: SearchResultItem[];
    follow_ups: SearchResultItem[];
    meetings: SearchResultItem[];
    team_members: SearchResultItem[];
}

/**
 * A displayed group of search results with a label and URL builder.
 */
interface ResultGroup {
    key: string;
    label: string;
    items: SearchResultItem[];
}

/**
 * Configuration passed from the Blade component via data attributes.
 */
interface GlobalSearchConfig {
    taskUrlPattern: string;
    noteUrlPattern: string;
    followUpUrlPattern: string;
    meetingUrlPattern: string;
    teamMemberUrlPattern: string;
}

/**
 * A flattened search result entry used for index-based keyboard navigation.
 */
interface FlatItem {
    groupKey: string;
    item: SearchResultItem;
}

/**
 * Labels for each entity type group in the results dropdown.
 */
const GROUP_LABELS: Record<string, string> = {
    tasks: 'Tasks',
    notes: 'Notes',
    follow_ups: 'Follow-ups',
    meetings: 'Meetings',
    team_members: 'Team Members',
};

/**
 * Minimum number of characters required before triggering a search.
 */
const MIN_QUERY_LENGTH = 2;

/**
 * Debounce delay in milliseconds.
 */
const DEBOUNCE_MS = 300;

/**
 * Alpine.js component that powers the global search bar in the application header.
 * Calls the search API with debouncing, displays grouped results in a dropdown,
 * and handles keyboard navigation and mobile responsive behavior.
 */
function globalSearch(config: GlobalSearchConfig): Record<string, unknown> {
    return {
        query: '',
        isOpen: false,
        isLoading: false,
        isExpanded: false,
        errorMessage: '',
        activeIndex: -1,
        groups: [] as ResultGroup[],
        debounceTimer: null as ReturnType<typeof setTimeout> | null,

        /**
         * Returns the display label for a search result item.
         */
        getItemLabel(item: SearchResultItem): string {
            return item.title ?? item.name ?? item.description ?? '';
        },

        /**
         * Returns a flat array of all result items across all groups for keyboard navigation.
         */
        getFlatItems(this: Record<string, unknown>): FlatItem[] {
            const self = this as Record<string, unknown>;
            const groups = self.groups as ResultGroup[];
            const flat: FlatItem[] = [];

            for (const group of groups) {
                for (const item of group.items) {
                    flat.push({ groupKey: group.key, item });
                }
            }

            return flat;
        },

        /**
         * Computes the flat index for a given group index and item index within that group.
         */
        getFlatIndex(this: Record<string, unknown>, groupIdx: number, itemIdx: number): number {
            const self = this as Record<string, unknown>;
            const groups = self.groups as ResultGroup[];
            let index = 0;

            for (let g = 0; g < groupIdx; g++) {
                index += groups[g].items.length;
            }

            return index + itemIdx;
        },

        /**
         * Builds the URL for a search result based on its entity type.
         */
        getItemUrl(groupKey: string, item: SearchResultItem): string {
            switch (groupKey) {
                case 'tasks':
                    return config.taskUrlPattern.replace(':id', String(item.id));
                case 'notes':
                    return config.noteUrlPattern.replace(':id', String(item.id));
                case 'follow_ups':
                    return config.followUpUrlPattern.replace(':id', String(item.id));
                case 'meetings':
                    return config.meetingUrlPattern.replace(':id', String(item.id));
                case 'team_members':
                    return config.teamMemberUrlPattern.replace(':id', String(item.id));
                default:
                    return '#';
            }
        },

        /**
         * Returns the DOM id for a result option at the given flat index.
         */
        getOptionId(index: number): string {
            return `search-result-${index}`;
        },

        /**
         * Handles input changes with debouncing.
         */
        onInput(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const query = self.query as string;

            if (self.debounceTimer !== null) {
                clearTimeout(self.debounceTimer as ReturnType<typeof setTimeout>);
            }

            if (query.length < MIN_QUERY_LENGTH) {
                self.isOpen = false;
                self.groups = [];
                self.errorMessage = '';
                self.activeIndex = -1;
                return;
            }

            self.debounceTimer = setTimeout(() => {
                void (self.performSearch as () => Promise<void>).call(self);
            }, DEBOUNCE_MS);
        },

        /**
         * Moves the active selection down by one item in the results list.
         */
        onArrowDown(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const flatItems = (self.getFlatItems as () => FlatItem[]).call(self);

            if (flatItems.length === 0) {
                return;
            }

            const current = self.activeIndex as number;
            self.activeIndex = current >= flatItems.length - 1 ? 0 : current + 1;
            (self.scrollActiveIntoView as () => void).call(self);
        },

        /**
         * Moves the active selection up by one item in the results list.
         */
        onArrowUp(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const flatItems = (self.getFlatItems as () => FlatItem[]).call(self);

            if (flatItems.length === 0) {
                return;
            }

            const current = self.activeIndex as number;
            self.activeIndex = current <= 0 ? flatItems.length - 1 : current - 1;
            (self.scrollActiveIntoView as () => void).call(self);
        },

        /**
         * Navigates to the currently active result when Enter is pressed.
         */
        onEnter(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const index = self.activeIndex as number;

            if (index < 0) {
                return;
            }

            const flatItems = (self.getFlatItems as () => FlatItem[]).call(self);

            if (index >= flatItems.length) {
                return;
            }

            const entry = flatItems[index];
            const url = (self.getItemUrl as (key: string, item: SearchResultItem) => string).call(
                self,
                entry.groupKey,
                entry.item,
            );

            (self.navigateTo as (url: string) => void).call(self, url);
        },

        /**
         * Scrolls the currently active result item into view within the dropdown.
         */
        scrollActiveIntoView(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const index = self.activeIndex as number;
            const refs = self.$refs as Record<string, HTMLElement>;
            const dropdown = refs.dropdown;

            if (!dropdown || index < 0) {
                return;
            }

            const optionId = (self.getOptionId as (i: number) => string).call(self, index);
            const element = dropdown.querySelector(`#${optionId}`);

            if (element) {
                element.scrollIntoView({ block: 'nearest' });
            }
        },

        /**
         * Executes the search API call and updates the results state.
         */
        async performSearch(this: Record<string, unknown>): Promise<void> {
            const self = this as Record<string, unknown>;
            const query = self.query as string;

            if (query.length < MIN_QUERY_LENGTH) {
                return;
            }

            self.isLoading = true;
            self.errorMessage = '';

            try {
                const response = await apiClient.get<SearchResults>(
                    `/api/v1/search?q=${encodeURIComponent(query)}`,
                );

                const data = response.data;
                const resultGroups: ResultGroup[] = [];

                for (const [key, items] of Object.entries(data)) {
                    if (items.length > 0) {
                        resultGroups.push({
                            key,
                            label: GROUP_LABELS[key] ?? key,
                            items,
                        });
                    }
                }

                self.groups = resultGroups;
                self.activeIndex = -1;
                self.isOpen = true;
            } catch (error: unknown) {
                const apiError = error as ApiError;
                self.errorMessage = apiError.message ?? 'An error occurred while searching.';
                self.groups = [];
                self.activeIndex = -1;
                self.isOpen = true;
            } finally {
                self.isLoading = false;
            }
        },

        /**
         * Navigates to the detail page for a search result.
         */
        navigateTo(this: Record<string, unknown>, url: string): void {
            const self = this as Record<string, unknown>;
            self.isOpen = false;
            self.activeIndex = -1;
            window.location.href = url;
        },

        /**
         * Closes the search dropdown and resets active selection.
         */
        close(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            self.isOpen = false;
            self.activeIndex = -1;
        },

        /**
         * Closes the dropdown and blurs the search input so keyboard shortcuts resume.
         * Only acts when the search dropdown is open or the input is focused.
         */
        closeAndFocus(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const refs = self.$refs as Record<string, HTMLElement>;
            const inputFocused = refs.searchInput && document.activeElement === refs.searchInput;

            if (!self.isOpen && !inputFocused) {
                return;
            }

            self.isOpen = false;
            self.activeIndex = -1;

            if (inputFocused) {
                refs.searchInput.blur();
            }
        },

        /**
         * Expands the mobile search bar and focuses the input.
         */
        expand(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            self.isExpanded = true;

            requestAnimationFrame(() => {
                const refs = self.$refs as Record<string, HTMLElement>;

                if (refs.searchInput) {
                    refs.searchInput.focus();
                }
            });
        },

        /**
         * Collapses the mobile search bar if the query is empty.
         */
        collapseIfEmpty(this: Record<string, unknown>): void {
            const self = this as Record<string, unknown>;
            const query = self.query as string;

            if (query.length === 0) {
                self.isExpanded = false;
                self.isOpen = false;
                self.activeIndex = -1;
            }
        },

        /**
         * Returns true when there are no results and no error.
         */
        get hasNoResults(): boolean {
            const self = this as unknown as Record<string, unknown>;
            const groups = self.groups as ResultGroup[];
            const errorMessage = self.errorMessage as string;
            const isLoading = self.isLoading as boolean;
            return groups.length === 0 && errorMessage === '' && !isLoading;
        },
    };
}

export { globalSearch };
export type { GlobalSearchConfig, SearchResults, SearchResultItem, ResultGroup };
