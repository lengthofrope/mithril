import { apiClient } from '../utils/api-client';
import type { ChartType, DataSource } from '../types/models';
import type { ApiError } from '../types/api';

/**
 * A single available data source with its display label and permitted chart types.
 */
interface DataSourceOption {
    value: DataSource;
    label: string;
    allowedChartTypes: ChartType[];
}

/**
 * Configuration for the widgetConfigurator Alpine component.
 */
interface WidgetConfiguratorConfig {
    storeEndpoint: string;
    dataSources: DataSourceOption[];
}

/**
 * A single column span option shown in the column span selector.
 */
interface ColumnSpanOption {
    value: number;
    label: string;
}

/**
 * Internal shape of the widgetConfigurator Alpine component.
 */
interface WidgetConfiguratorComponent {
    isOpen: boolean;
    isSaving: boolean;
    hasError: boolean;
    selectedSource: DataSource | '';
    selectedChartType: ChartType | '';
    selectedColumnSpan: number;
    showOnAnalytics: boolean;
    showOnDashboard: boolean;
    availableChartTypes: ChartType[];
    open(): void;
    close(): void;
    onSourceChange(): void;
    save(): Promise<void>;
    readonly columnSpanOptions: ColumnSpanOption[];
}

/**
 * Alpine.js component that drives the widget creation modal.
 * Manages available chart types based on the selected data source,
 * and posts the new widget configuration to the backend on save.
 */
function widgetConfigurator(config: WidgetConfiguratorConfig): Record<string, unknown> {
    return {
        isOpen: false,
        isSaving: false,
        hasError: false,
        selectedSource: '' as DataSource | '',
        selectedChartType: '' as ChartType | '',
        selectedColumnSpan: 1,
        showOnAnalytics: true,
        showOnDashboard: false,
        availableChartTypes: [] as ChartType[],
        dataSources: config.dataSources,

        /**
         * Resets all form fields to their defaults and opens the configurator modal.
         */
        open(this: WidgetConfiguratorComponent): void {
            this.isSaving = false;
            this.hasError = false;
            this.selectedSource = '';
            this.selectedChartType = '';
            this.selectedColumnSpan = 1;
            this.showOnAnalytics = true;
            this.showOnDashboard = false;
            this.availableChartTypes = [];
            this.isOpen = true;
        },

        /**
         * Closes the configurator modal without saving.
         */
        close(this: WidgetConfiguratorComponent): void {
            this.isOpen = false;
        },

        /**
         * Updates the available chart types when the selected data source changes.
         * Auto-selects the first permitted chart type.
         */
        onSourceChange(this: WidgetConfiguratorComponent): void {
            const found = config.dataSources.find((ds) => ds.value === this.selectedSource);

            if (found === undefined) {
                this.availableChartTypes = [];
                this.selectedChartType = '';
                return;
            }

            this.availableChartTypes = found.allowedChartTypes;
            this.selectedChartType = found.allowedChartTypes[0] ?? '';
        },

        /**
         * Posts the configured widget to the backend and reloads the page on success.
         */
        async save(this: WidgetConfiguratorComponent): Promise<void> {
            this.isSaving = true;
            this.hasError = false;

            try {
                await apiClient.post(config.storeEndpoint, {
                    data_source: this.selectedSource,
                    chart_type: this.selectedChartType,
                    column_span: this.selectedColumnSpan,
                    show_on_analytics: this.showOnAnalytics,
                    show_on_dashboard: this.showOnDashboard,
                });

                window.location.reload();
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[widgetConfigurator] Save failed:', apiError.message);
                this.hasError = true;
            } finally {
                this.isSaving = false;
            }
        },

        /**
         * Returns the available column span options for the column span selector.
         */
        get columnSpanOptions(): ColumnSpanOption[] {
            return [
                { value: 1, label: '1/3' },
                { value: 2, label: '2/3' },
                { value: 3, label: 'Full' },
            ];
        },
    };
}

export { widgetConfigurator };
export type { WidgetConfiguratorConfig, DataSourceOption, ColumnSpanOption };
