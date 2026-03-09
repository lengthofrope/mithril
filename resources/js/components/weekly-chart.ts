import ApexCharts from 'apexcharts';

/**
 * Configuration for the weeklyChart Alpine component.
 */
interface WeeklyChartConfig {
    chartType: 'donut' | 'bar_horizontal';
    labels: string[];
    series: number[];
    colors: string[];
}

/**
 * Internal shape of the weeklyChart Alpine component.
 */
interface WeeklyChartComponent {
    chartInstance: ApexCharts | null;
    init(): void;
    destroy(): void;
    $refs: { chart: HTMLElement };
}

/**
 * Reads the current theme mode from the document root element.
 */
function readThemeMode(): 'light' | 'dark' {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

/**
 * Builds ApexCharts options for the given chart type and inline data.
 */
function buildOptions(config: WeeklyChartConfig): ApexCharts.ApexOptions {
    const mode = readThemeMode();

    const base: ApexCharts.ApexOptions = {
        colors: config.colors,
        chart: {
            height: 220,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        theme: { mode },
        responsive: [
            {
                breakpoint: 640,
                options: {
                    chart: { height: 180 },
                },
            },
        ],
    };

    if (config.chartType === 'donut') {
        return {
            ...base,
            chart: { ...base.chart, type: 'donut' },
            series: config.series,
            labels: config.labels,
            legend: { position: 'bottom' },
            dataLabels: {
                formatter: (val: number, opts: { w: { config: { series: number[] } }; seriesIndex: number }): string => {
                    return String(opts.w.config.series[opts.seriesIndex]);
                },
            },
        };
    }

    return {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '60%',
                distributed: true,
            },
        },
        series: [{ name: 'Count', data: config.series }],
        xaxis: {
            categories: config.labels,
            labels: {
                formatter: (val: string): string => String(Math.round(Number(val))),
            },
        },
        yaxis: {
            labels: {
                style: { fontSize: '12px' },
            },
        },
        legend: { show: false },
        tooltip: {
            y: {
                formatter: (val: number): string => String(val),
            },
        },
    };
}

/**
 * Lightweight Alpine.js component that renders an ApexCharts chart from inline data.
 * Unlike analyticsChart, this does not fetch from an API endpoint.
 */
function weeklyChart(config: WeeklyChartConfig): Record<string, unknown> {
    let themeObserver: MutationObserver | null = null;

    return {
        chartInstance: null as ApexCharts | null,

        /**
         * Renders the chart on mount and observes dark mode changes.
         */
        init(this: WeeklyChartComponent): void {
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

            const options = buildOptions(config);
            this.chartInstance = new ApexCharts(this.$refs.chart, options);
            void this.chartInstance.render();
        },

        /**
         * Cleans up the ApexCharts instance and MutationObserver on Alpine destroy.
         */
        destroy(this: WeeklyChartComponent): void {
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

export { weeklyChart };
export type { WeeklyChartConfig };
