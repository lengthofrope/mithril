import Sortable from 'sortablejs';
import { apiClient } from '../utils/api-client';
import type { ReorderItem } from '../types/api';
import type { ApiError } from '../types/api';

/**
 * Configuration for the analyticsBoard Alpine component.
 */
interface AnalyticsBoardConfig {
    context: 'analytics' | 'dashboard';
    reorderEndpoint: string;
    widgetEndpoint: string;
}

/**
 * Internal shape of the analyticsBoard Alpine component.
 */
interface AnalyticsBoardComponent {
    isReordering: boolean;
    hasReorderError: boolean;
    handleReorder(): Promise<void>;
    addWidget(formData: Record<string, unknown>): Promise<void>;
    deleteWidget(widgetId: number): Promise<void>;
    updateWidget(widgetId: number, data: Record<string, unknown>): Promise<void>;
    $refs: { widgetGrid: HTMLElement };
}

/**
 * Resolves the sort field name based on the board context.
 */
function resolveSortField(context: 'analytics' | 'dashboard'): string {
    return context === 'analytics' ? 'sort_order_analytics' : 'sort_order_dashboard';
}

/**
 * Collects widget IDs and their new sort positions from the grid's direct children.
 */
function collectWidgetItems(grid: HTMLElement): ReorderItem[] {
    return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-widget-id]'))
        .map((el, index) => ({
            id: Number(el.dataset['widgetId']),
            sort_order: index,
        }));
}

/**
 * Alpine.js component that manages the analytics widget grid.
 * Enables drag-and-drop reordering via SortableJS, and provides
 * methods for adding, deleting, and updating widgets.
 */
function analyticsBoard(config: AnalyticsBoardConfig): Record<string, unknown> {
    return {
        isReordering: false,
        hasReorderError: false,

        /**
         * Initialises SortableJS on the widget grid ref element.
         */
        init(this: AnalyticsBoardComponent): void {
            const grid = this.$refs.widgetGrid;

            if (grid === undefined) {
                console.warn('[analyticsBoard] widgetGrid ref not found');
                return;
            }

            const self = this;

            Sortable.create(grid, {
                animation: 150,
                handle: '.drag-handle',
                onEnd(): void {
                    void self.handleReorder();
                },
            });
        },

        /**
         * Collects the current widget order from the DOM and sends it to the backend.
         */
        async handleReorder(this: AnalyticsBoardComponent): Promise<void> {
            const grid = this.$refs.widgetGrid;
            const items = collectWidgetItems(grid);

            this.isReordering = true;
            this.hasReorderError = false;

            try {
                await apiClient.post(config.reorderEndpoint, {
                    model_type: 'analytics_widget',
                    sort_field: resolveSortField(config.context),
                    items,
                });
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsBoard] Reorder failed:', apiError.message);
                this.hasReorderError = true;
            } finally {
                this.isReordering = false;
            }
        },

        /**
         * Sends a POST request to create a new widget, then reloads the page.
         */
        async addWidget(formData: Record<string, unknown>): Promise<void> {
            try {
                await apiClient.post(config.widgetEndpoint, formData);
                window.location.reload();
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsBoard] Add widget failed:', apiError.message);
            }
        },

        /**
         * Sends a DELETE request for the given widget ID and removes its DOM element.
         */
        async deleteWidget(this: AnalyticsBoardComponent, widgetId: number): Promise<void> {
            try {
                await apiClient.delete(`${config.widgetEndpoint}/${widgetId}`);

                const el = this.$refs.widgetGrid.querySelector<HTMLElement>(`[data-widget-id="${widgetId}"]`);

                if (el !== null) {
                    el.remove();
                }
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsBoard] Delete widget failed:', apiError.message);
            }
        },

        /**
         * Sends a PATCH request to update settings for the given widget ID.
         */
        async updateWidget(widgetId: number, data: Record<string, unknown>): Promise<void> {
            try {
                await apiClient.patch(`${config.widgetEndpoint}/${widgetId}`, data);
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsBoard] Update widget failed:', apiError.message);
            }
        },
    };
}

export { analyticsBoard };
export type { AnalyticsBoardConfig };
