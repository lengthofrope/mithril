import ApexCharts from 'apexcharts';
import { apiClient } from '../utils/api-client';
import type { ChartType, DataSource, ChartData } from '../types/models';
import type { ApiError } from '../types/api';

/**
 * Configuration for the analyticsChart Alpine component.
 */
interface AnalyticsChartConfig {
    widgetId: number;
    chartType: ChartType;
    dataSource: DataSource;
    dataEndpoint: string;
    title: string;
}

/**
 * Response envelope for the analytics data endpoint.
 */
interface ChartDataResponse {
    sources: Record<string, ChartData>;
}

/**
 * Internal shape of the analyticsChart Alpine component.
 */
interface AnalyticsChartComponent {
    isLoading: boolean;
    hasError: boolean;
    chartInstance: ApexCharts | null;
    init(): void;
    renderChart(data: ChartData): void;
    updateChartType(newType: ChartType): Promise<void>;
    destroy(): void;
    $refs: { chart: HTMLElement };
    $nextTick(callback: () => void): void;
}

/**
 * Reads the current theme mode from the document root element.
 */
function readThemeMode(): 'light' | 'dark' {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

/**
 * Builds ApexCharts options for the given chart type and data.
 */
function buildChartOptions(chartType: ChartType, data: ChartData): ApexCharts.ApexOptions {
    const mode = readThemeMode();

    const base: ApexCharts.ApexOptions = {
        colors: data.colors,
        chart: {
            height: 300,
            background: 'transparent',
        },
        theme: {
            mode,
        },
        legend: {
            position: 'bottom',
        },
        responsive: [
            {
                breakpoint: 640,
                options: {
                    chart: {
                        height: 250,
                    },
                },
            },
        ],
    };

    if (chartType === 'donut') {
        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'donut',
            },
            series: data.series,
            labels: data.labels,
        };
    }

    const seriesData = [{ name: 'Count', data: data.series }];
    const xaxis: ApexCharts.ApexOptions['xaxis'] = { categories: data.labels };

    if (chartType === 'bar_horizontal') {
        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'bar',
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                },
            },
            series: seriesData,
            xaxis,
            legend: {
                show: false,
            },
        };
    }

    if (chartType === 'stacked_bar') {
        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'bar',
                stacked: true,
            },
            series: seriesData,
            xaxis,
            legend: {
                show: false,
            },
        };
    }

    return {
        ...base,
        chart: {
            ...base.chart,
            type: 'bar',
        },
        series: seriesData,
        xaxis,
        legend: {
            show: false,
        },
    };
}

/**
 * Alpine.js component that renders an ApexCharts chart for an analytics widget.
 * Fetches data from the configured endpoint on mount, renders the chart, and
 * observes dark mode changes to update the chart theme without a full re-render.
 */
function analyticsChart(config: AnalyticsChartConfig): Record<string, unknown> {
    let themeObserver: MutationObserver | null = null;

    return {
        isLoading: true,
        hasError: false,
        chartInstance: null as ApexCharts | null,

        /**
         * Fetches widget data and renders the chart on Alpine mount.
         */
        async init(this: AnalyticsChartComponent): Promise<void> {
            const self = this;

            themeObserver = new MutationObserver(() => {
                if (self.chartInstance === null) {
                    return;
                }

                void self.chartInstance.updateOptions({ theme: { mode: readThemeMode() } });
            });

            themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            });

            try {
                const response = await apiClient.get<ChartDataResponse>(
                    `${config.dataEndpoint}?sources[]=${config.dataSource}`,
                );

                const data = response.data.sources[config.dataSource];

                if (data === undefined) {
                    this.hasError = true;
                    this.isLoading = false;
                    return;
                }

                this.isLoading = false;
                this.$nextTick(() => {
                    this.renderChart(data);
                });
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsChart] Data fetch failed:', apiError.message);
                this.hasError = true;
                this.isLoading = false;
            }
        },

        /**
         * Creates and mounts an ApexCharts instance into the `chart` ref element.
         */
        renderChart(this: AnalyticsChartComponent, data: ChartData): void {
            if (this.chartInstance !== null) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            const options = buildChartOptions(config.chartType, data);
            this.chartInstance = new ApexCharts(this.$refs.chart, options);

            void this.chartInstance.render();
        },

        /**
         * Destroys the current chart, updates the chart type on the config, and re-fetches data.
         */
        async updateChartType(this: AnalyticsChartComponent, newType: ChartType): Promise<void> {
            if (this.chartInstance !== null) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            config = { ...config, chartType: newType };

            this.isLoading = true;
            this.hasError = false;

            try {
                const response = await apiClient.get<ChartDataResponse>(
                    `${config.dataEndpoint}?sources[]=${config.dataSource}`,
                );

                const data = response.data.sources[config.dataSource];

                if (data === undefined) {
                    this.hasError = true;
                    this.isLoading = false;
                    return;
                }

                this.isLoading = false;
                this.$nextTick(() => {
                    this.renderChart(data);
                });
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[analyticsChart] Chart type update failed:', apiError.message);
                this.hasError = true;
                this.isLoading = false;
            }
        },

        /**
         * Cleans up the ApexCharts instance and MutationObserver on Alpine destroy.
         */
        destroy(this: AnalyticsChartComponent): void {
            if (this.chartInstance !== null) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            if (themeObserver !== null) {
                themeObserver.disconnect();
                themeObserver = null;
            }
        },
    };
}

export { analyticsChart };
export type { AnalyticsChartConfig };
