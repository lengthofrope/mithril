import ApexCharts from 'apexcharts';
import { apiClient } from '../utils/api-client';
import type { ChartType, DataSource, ChartData, TimeSeriesChartData, TimeRange } from '../types/models';
import type { ApiError } from '../types/api';

/**
 * Configuration for the analyticsChart Alpine component.
 */
interface AnalyticsChartConfig {
    widgetId: number;
    chartType: ChartType;
    dataSource: DataSource;
    dataEndpoint: string;
    updateEndpoint: string;
    title: string;
    timeRange: TimeRange | null;
}

/**
 * Union type for chart data — either point-in-time or time-series.
 */
type AnyChartData = ChartData | TimeSeriesChartData;

/**
 * Response envelope for the analytics data endpoint.
 */
interface ChartDataResponse {
    sources: Record<string, AnyChartData>;
}

/**
 * Internal shape of the analyticsChart Alpine component.
 */
interface AnalyticsChartComponent {
    isLoading: boolean;
    hasError: boolean;
    chartInstance: ApexCharts | null;
    init(): void;
    renderChart(data: AnyChartData): void;
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
 * Type guard to check if chart data is time-series (multi-series with named series).
 */
function isTimeSeriesData(data: AnyChartData): data is TimeSeriesChartData {
    return Array.isArray(data.series) &&
        data.series.length > 0 &&
        typeof data.series[0] === 'object' &&
        'name' in (data.series[0] as Record<string, unknown>);
}

/**
 * Builds ApexCharts options for the given chart type and data.
 */
function buildChartOptions(chartType: ChartType, data: AnyChartData): ApexCharts.ApexOptions {
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

    if (chartType === 'line' && isTimeSeriesData(data)) {
        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'line',
            },
            series: data.series,
            xaxis: {
                categories: data.labels,
                labels: {
                    rotate: -45,
                    formatter: (value: string): string => {
                        const date = new Date(value);
                        return `${date.getDate()}/${date.getMonth() + 1}`;
                    },
                },
            },
            stroke: {
                curve: 'smooth',
                width: 2,
            },
            markers: {
                size: 0,
                hover: {
                    size: 5,
                },
            },
            tooltip: {
                x: {
                    formatter: (val: number, opts?: { w: { globals: { categoryLabels: string[] } } }): string => {
                        const label = opts?.w?.globals?.categoryLabels?.[val - 1] ?? '';

                        if (label === '') {
                            return String(val);
                        }

                        const date = new Date(label);

                        return date.toLocaleDateString('en-GB', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                        });
                    },
                },
            },
        };
    }

    if (chartType === 'donut') {
        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'donut',
            },
            series: data.series as number[],
            labels: data.labels,
        };
    }

    const seriesData = [{ name: 'Count', data: data.series as number[] }];
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
 * Builds the query string for the data endpoint, including time range for time-series sources.
 */
function buildDataUrl(config: AnalyticsChartConfig): string {
    let url = `${config.dataEndpoint}?sources[]=${config.dataSource}`;

    if (config.timeRange !== null) {
        url += `&time_range=${config.timeRange}`;
    }

    return url;
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
                const response = await apiClient.get<ChartDataResponse>(buildDataUrl(config));

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
        renderChart(this: AnalyticsChartComponent, data: AnyChartData): void {
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
                const [response] = await Promise.all([
                    apiClient.get<ChartDataResponse>(buildDataUrl(config)),
                    apiClient.patch(config.updateEndpoint, { chart_type: newType }),
                ]);

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
