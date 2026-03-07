import Sortable from 'sortablejs';
import { apiClient } from '../utils/api-client';
import type { MoveItem, ReorderItem } from '../types/api';
import type { ApiError } from '../types/api';

/**
 * Configuration for the sortableKanban Alpine component.
 */
interface SortableKanbanConfig {
    containerSelector: string;
    modelType: string;
    endpoint: string;
    reorderEndpoint: string;
    statusField: string;
}

/**
 * Maps status keys to their display labels and CSS classes (must match status-badge.blade.php).
 */
const statusStyles: Record<string, { label: string; colorClass: string }> = {
    open:        { label: 'Open',        colorClass: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400' },
    in_progress: { label: 'In Progress', colorClass: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400' },
    waiting:     { label: 'Waiting',     colorClass: 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400' },
    done:        { label: 'Done',        colorClass: 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500' },
};

/**
 * Updates the status badge inside a card element to reflect the new status.
 */
function updateStatusBadge(card: HTMLElement, status: string): void {
    const badge = card.querySelector<HTMLElement>('[data-status-badge]');

    if (badge === null) {
        return;
    }

    const style = statusStyles[status];

    if (style === undefined) {
        return;
    }

    badge.className = `inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${style.colorClass}`;
    badge.textContent = style.label;
}

/**
 * Reads the column status value stored on the column's container element.
 */
function readColumnStatus(column: HTMLElement): string | null {
    return column.dataset['kanbanStatus'] ?? null;
}

/**
 * Alpine.js component that enables kanban-style drag and drop across columns.
 * On drop, sends both the new sort_order and the updated status to the backend.
 */
function sortableKanban(config: SortableKanbanConfig): Record<string, unknown> {
    return {
        isMoving: false,
        hasMoveError: false,

        /**
         * Initialises one SortableJS instance per column found within the container.
         */
        init(this: { isMoving: boolean; hasMoveError: boolean; handleMove: (event: Sortable.SortableEvent) => Promise<void> }): void {
            const container = document.querySelector<HTMLElement>(config.containerSelector);

            if (container === null) {
                console.warn('[sortableKanban] Container not found:', config.containerSelector);
                return;
            }

            const columns = container.querySelectorAll<HTMLElement>('[data-kanban-status]');
            const self = this;

            columns.forEach((column) => {
                Sortable.create(column, {
                    group: config.containerSelector,
                    animation: 150,
                    onEnd(event: Sortable.SortableEvent): void {
                        void self.handleMove(event);
                    },
                });
            });
        },

        /**
         * Determines the item's new column and position, then sends the move payload.
         */
        async handleMove(
            this: { isMoving: boolean; hasMoveError: boolean },
            event: Sortable.SortableEvent,
        ): Promise<void> {
            const item = event.item as HTMLElement;
            const fromColumn = event.from as HTMLElement;
            const toColumn = event.to as HTMLElement;

            const id = Number(item.dataset['id']);
            const fromStatus = readColumnStatus(fromColumn);
            const toStatus = readColumnStatus(toColumn);

            const sortOrder = Array.from(toColumn.querySelectorAll<HTMLElement>(':scope > [data-id]'))
                .findIndex((el) => el.dataset['id'] === item.dataset['id']);

            this.isMoving = true;
            this.hasMoveError = false;

            try {
                if (fromStatus === toStatus) {
                    const items: ReorderItem[] = Array.from(
                        toColumn.querySelectorAll<HTMLElement>(':scope > [data-id]'),
                    ).map((el, index) => ({
                        id: Number(el.dataset['id']),
                        sort_order: index,
                    }));

                    await apiClient.post(config.reorderEndpoint, {
                        model_type: config.modelType,
                        items,
                    });
                } else {
                    const payload: MoveItem = {
                        id,
                        from_group: fromStatus !== null ? Number(fromStatus) : null,
                        to_group: toStatus !== null ? Number(toStatus) : null,
                        sort_order: sortOrder,
                    };

                    await apiClient.post(config.endpoint, {
                        model_type: config.modelType,
                        [config.statusField]: toStatus,
                        ...payload,
                    });

                    if (toStatus !== null) {
                        updateStatusBadge(item, toStatus);
                    }
                }
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[sortableKanban] Move failed:', apiError.message);
                this.hasMoveError = true;
            } finally {
                this.isMoving = false;
            }
        },
    };
}

export { sortableKanban };
export type { SortableKanbanConfig };
