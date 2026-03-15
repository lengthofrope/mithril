import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the tagEditor Alpine component.
 */
interface TagEditorConfig {
    endpoint: string;
    initialTags: string[];
}

/**
 * Save status indicator.
 */
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Alpine.js component for editing tags on a note.
 *
 * Supports adding tags via Enter/comma, removing by click,
 * and auto-syncs to the API on every change.
 */
function tagEditor(config: TagEditorConfig): Record<string, unknown> {
    return {
        tags: [...config.initialTags] as string[],
        input: '' as string,
        status: 'idle' as SaveStatus,

        /**
         * Handles keydown events on the tag input field.
         * Adds a tag on Enter or comma, removes last tag on Backspace when input is empty.
         */
        handleKeydown(this: {
            input: string;
            tags: string[];
            addTag: () => void;
            removeTag: (index: number) => void;
        }, event: KeyboardEvent): void {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                this.addTag();
            }

            if (event.key === 'Backspace' && this.input === '' && this.tags.length > 0) {
                this.removeTag(this.tags.length - 1);
            }
        },

        /**
         * Adds the current input value as a new tag if it is not empty or a duplicate.
         */
        addTag(this: { input: string; tags: string[]; sync: () => Promise<void> }): void {
            const tag = this.input.trim().toLowerCase().replace(/,/g, '');

            if (tag === '' || this.tags.includes(tag)) {
                this.input = '';
                return;
            }

            this.tags.push(tag);
            this.input = '';
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
