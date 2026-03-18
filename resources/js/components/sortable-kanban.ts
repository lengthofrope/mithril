import Sortable from 'sortablejs';
import { apiClient } from '../utils/api-client';
import { registerDoubleClickNavigation } from '../utils/double-click-navigation';
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
 * Updates the inline-select-pill Alpine component for the status field on a card.
 */
function updateInlineStatus(card: HTMLElement, field: string, newValue: string): void {
    const pills = card.querySelectorAll<HTMLElement>('[x-data*="inlineSelect"]');

    for (const pill of pills) {
        const alpineData = (pill as unknown as { _x_dataStack?: Array<Record<string, unknown>> })._x_dataStack?.[0];

        if (alpineData === undefined) {
            continue;
        }

        const xData = pill.getAttribute('x-data') ?? '';

        if (xData.includes(`field: '${field}'`)) {
            alpineData['value'] = newValue;
            return;
        }
    }
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
                    handle: '.drag-handle',
                    emptyInsertThreshold: 80,
                    onEnd(event: Sortable.SortableEvent): void {
                        void self.handleMove(event);
                    },
                });

                registerDoubleClickNavigation(column);
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
                        updateInlineStatus(item, config.statusField, toStatus);
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
