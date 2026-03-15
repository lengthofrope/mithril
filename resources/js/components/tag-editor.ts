import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the tagEditor Alpine component.
 */
interface TagEditorConfig {
    endpoint: string;
    initialTags: string[];
    allTags: string[];
}

/**
 * Save status indicator.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Alpine.js component for editing tags on a note.
 *
 * Supports adding tags via Enter/comma, removing by click,
 * autocomplete suggestions from existing tags, and auto-syncs
 * to the API on every change.
 */
function tagEditor(config: TagEditorConfig): Record<string, unknown> {
    return {
        tags: [...config.initialTags] as string[],
        input: '' as string,
        status: 'idle' as SaveStatus,
        showSuggestions: false as boolean,
        selectedIndex: -1 as number,

        /**
         * Returns matching tags that are not already applied, filtered by current input.
         */
        get suggestions(): string[] {
            const query = (this as unknown as { input: string }).input.trim().toLowerCase();
            const current = (this as unknown as { tags: string[] }).tags;

            if (query === '') {
                return [];
            }

            return config.allTags.filter(
                (tag: string) => tag.includes(query) && !current.includes(tag),
            );
        },

        /**
         * Handles keydown events on the tag input field.
         * Adds a tag on Enter or comma, navigates suggestions with arrows,
         * removes last tag on Backspace when input is empty.
         */
        handleKeydown(this: {
            input: string;
            tags: string[];
            showSuggestions: boolean;
            selectedIndex: number;
            suggestions: string[];
            addTag: () => void;
            selectSuggestion: (tag: string) => void;
            removeTag: (index: number) => void;
        }, event: KeyboardEvent): void {
            if (event.key === 'ArrowDown' && this.showSuggestions && this.suggestions.length > 0) {
                event.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.suggestions.length - 1);
                return;
            }

            if (event.key === 'ArrowUp' && this.showSuggestions && this.suggestions.length > 0) {
                event.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                return;
            }

            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();

                if (this.showSuggestions && this.selectedIndex >= 0 && this.suggestions[this.selectedIndex]) {
                    this.selectSuggestion(this.suggestions[this.selectedIndex]);
                    return;
                }

                this.addTag();
                return;
            }

            if (event.key === 'Escape' && this.showSuggestions) {
                this.showSuggestions = false;
                this.selectedIndex = -1;
                return;
            }

            if (event.key === 'Backspace' && this.input === '' && this.tags.length > 0) {
                this.removeTag(this.tags.length - 1);
            }
        },

        /**
         * Handles input changes to show or hide the suggestion dropdown.
         */
        handleInput(this: { input: string; showSuggestions: boolean; selectedIndex: number; suggestions: string[] }): void {
            this.showSuggestions = this.input.trim() !== '' && this.suggestions.length > 0;
            this.selectedIndex = -1;
        },

        /**
         * Selects a suggestion from the autocomplete dropdown.
         */
        selectSuggestion(this: { tags: string[]; input: string; showSuggestions: boolean; selectedIndex: number; sync: () => Promise<void> }, tag: string): void {
            if (!this.tags.includes(tag)) {
                this.tags.push(tag);
                void this.sync();
            }

            this.input = '';
            this.showSuggestions = false;
            this.selectedIndex = -1;
        },

        /**
         * Adds the current input value as a new tag if it is not empty or a duplicate.
         */
        addTag(this: { input: string; tags: string[]; showSuggestions: boolean; selectedIndex: number; sync: () => Promise<void> }): void {
            const tag = this.input.trim().toLowerCase().replace(/,/g, '');

            if (tag === '' || this.tags.includes(tag)) {
                this.input = '';
                this.showSuggestions = false;
                return;
            }

            this.tags.push(tag);
            this.input = '';
            this.showSuggestions = false;
            this.selectedIndex = -1;
            void this.sync();
        },

        /**
         * Removes the tag at the given index.
         */
        removeTag(this: { tags: string[]; sync: () => Promise<void> }, index: number): void {
            this.tags.splice(index, 1);
            void this.sync();
        },

        /**
         * Syncs the current tags to the API endpoint.
         */
        async sync(this: { tags: string[]; status: SaveStatus }): Promise<void> {
            this.status = 'saving';

            try {
                await apiClient.put(config.endpoint, { tags: this.tags });
                this.status = 'saved';
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[tagEditor] Sync failed:', apiError.message);
                this.status = 'error';
            }
        },
    };
}

export { tagEditor };
export type { TagEditorConfig };
