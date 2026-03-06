import Sortable from 'sortablejs';
import { apiClient } from '../utils/api-client';
import type { ReorderItem } from '../types/api';
import type { ApiError } from '../types/api';

/**
 * Configuration for the sortableList Alpine component.
 */
interface SortableListConfig {
    containerSelector: string;
    modelType: string;
    endpoint: string;
    group?: string;
}

/**
 * Alpine.js component that wraps SortableJS to enable drag-and-drop reordering.
 * Sends updated sort order to the backend via POST after each drop.
 * Supports cross-list drag using an optional group name.
 */
function sortableList(config: SortableListConfig): Record<string, unknown> {
    return {
        isReordering: false,
        hasReorderError: false,

        /**
         * Initialises the SortableJS instance on the configured container element.
         */
        init(this: { isReordering: boolean; hasReorderError: boolean; handleReorder: (sortable: Sortable) => Promise<void> }): void {
            const container = document.querySelector<HTMLElement>(config.containerSelector);

            if (container === null) {
                console.warn('[sortableList] Container not found:', config.containerSelector);
                return;
            }

            const self = this;

            Sortable.create(container, {
                group: config.group,
                animation: 150,
                onEnd(event: Sortable.SortableEvent): void {
                    void self.handleReorder(event.to as unknown as Sortable);
                },
            });
        },

        /**
         * Collects current sort order from DOM data attributes and sends to the backend.
         */
        async handleReorder(this: { isReordering: boolean; hasReorderError: boolean }): Promise<void> {
            const container = document.querySelector<HTMLElement>(config.containerSelector);

            if (container === null) {
                return;
            }

            const items: ReorderItem[] = Array.from(
                container.querySelectorAll<HTMLElement>('[data-id]'),
            ).map((el, index) => ({
                id: Number(el.dataset['id']),
                sort_order: index,
            }));

            this.isReordering = true;
            this.hasReorderError = false;

            try {
                await apiClient.post(config.endpoint, {
                    model_type: config.modelType,
                    items,
                });
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[sortableList] Reorder failed:', apiError.message);
                this.hasReorderError = true;
            } finally {
                this.isReordering = false;
            }
        },
    };
}

export { sortableList };
export type { SortableListConfig };
