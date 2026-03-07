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
    moveEndpoint?: string;
}

/**
 * Internal shape of the sortableList Alpine component.
 */
interface SortableListComponent {
    isReordering: boolean;
    hasReorderError: boolean;
    handleReorder(container: HTMLElement): Promise<void>;
    handleCrossListMove(event: Sortable.SortableEvent): Promise<void>;
}

/**
 * Reads the group ID stored on a sortable container element.
 * Returns the numeric group ID, or null for ungrouped (empty string or "0").
 */
function readGroupId(container: HTMLElement): number | null {
    const value = container.dataset['groupId'];

    if (value === undefined || value === '' || value === '0') {
        return null;
    }

    return Number(value);
}

/**
 * Collects reorder items from a container's direct children with data-id attributes.
 */
function collectReorderItems(container: HTMLElement): ReorderItem[] {
    return Array.from(container.querySelectorAll<HTMLElement>(':scope > [data-id]'))
        .map((el, index) => ({
            id: Number(el.dataset['id']),
            sort_order: index,
        }));
}

/**
 * Alpine.js component that wraps SortableJS to enable drag-and-drop reordering.
 * Sends updated sort order to the backend via POST after each drop.
 * Supports cross-list drag using an optional group name and move endpoint.
 */
function sortableList(config: SortableListConfig): Record<string, unknown> {
    return {
        isReordering: false,
        hasReorderError: false,

        /**
         * Initialises the SortableJS instance on the configured container element.
         */
        init(this: SortableListComponent): void {
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
                    if (config.moveEndpoint && event.from !== event.to) {
                        void self.handleCrossListMove(event);
                    } else {
                        void self.handleReorder(event.to as HTMLElement);
                    }
                },
            });
        },

        /**
         * Collects current sort order from DOM data attributes and sends to the backend.
         */
        async handleReorder(this: SortableListComponent, container: HTMLElement): Promise<void> {
            const items = collectReorderItems(container);

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

        /**
         * Handles a drag from one group container to another.
         * Updates the item's group assignment via the move endpoint,
         * then reorders both the source and target containers.
         */
        async handleCrossListMove(this: SortableListComponent, event: Sortable.SortableEvent): Promise<void> {
            const item = event.item as HTMLElement;
            const fromContainer = event.from as HTMLElement;
            const toContainer = event.to as HTMLElement;

            const taskId = Number(item.dataset['id']);
            const toGroupId = readGroupId(toContainer);

            this.isReordering = true;
            this.hasReorderError = false;

            try {
                const movePayload: Record<string, unknown> = { id: taskId };

                if (toGroupId === null) {
                    movePayload['clear_group'] = true;
                } else {
                    movePayload['task_group_id'] = toGroupId;
                }

                await apiClient.post(config.moveEndpoint as string, movePayload);

                const toItems = collectReorderItems(toContainer);

                if (toItems.length > 0) {
                    await apiClient.post(config.endpoint, {
                        model_type: config.modelType,
                        items: toItems,
                    });
                }

                const fromItems = collectReorderItems(fromContainer);

                if (fromItems.length > 0) {
                    await apiClient.post(config.endpoint, {
                        model_type: config.modelType,
                        items: fromItems,
                    });
                }
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[sortableList] Cross-list move failed:', apiError.message);
                this.hasReorderError = true;
            } finally {
                this.isReordering = false;
            }
        },
    };
}

export { sortableList };
export type { SortableListConfig };
