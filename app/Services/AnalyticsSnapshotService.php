<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TimeSeriesChartData;
use App\Models\AnalyticsSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Queries the analytics_snapshots table to produce time-series data suitable for line charts.
 *
 * All public methods accept a time range string ('7d', '30d', or '90d') and return
 * a TimeSeriesChartData instance with a label per day and one named series per metric.
 * Days with no snapshot row are filled with zero so the chart always covers the full range.
 */
class AnalyticsSnapshotService
{
    /**
     * Number of days covered by each supported time range key.
     *
     * @var array<string, int>
     */
    private const TIME_RANGE_DAYS = [
        '7d'  => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * Task status metrics, in display order.
     *
     * @var array<string, array{name: string, color: string}>
     */
    private const TASK_STATUS_METRICS = [
        'tasks_status_open'        => ['name' => 'Open',        'color' => '#3b82f6'],
        'tasks_status_in_progress' => ['name' => 'In Progress', 'color' => '#f59e0b'],
        'tasks_status_waiting'     => ['name' => 'Waiting',     'color' => '#a855f7'],
        'tasks_status_done'        => ['name' => 'Done',        'color' => '#22c55e'],
    ];

    /**
     * Follow-up status metrics, in display order.
     *
     * @var array<string, array{name: string, color: string}>
     */
    private const FOLLOW_UP_STATUS_METRICS = [
        'follow_ups_status_open'    => ['name' => 'Open',    'color' => '#3b82f6'],
        'follow_ups_status_snoozed' => ['name' => 'Snoozed', 'color' => '#f59e0b'],
        'follow_ups_status_done'    => ['name' => 'Done',    'color' => '#22c55e'],
    ];

    /**
     * Return a time-series chart showing each task status count over the given date range.
     *
     * Series: Open, In Progress, Waiting, Done.
     *
     * @param int    $userId    The authenticated user whose snapshots to query.
     * @param string $timeRange One of '7d', '30d', or '90d'.
     * @return TimeSeriesChartData
     */
    public function tasksOverTime(int $userId, string $timeRange): TimeSeriesChartData
    {
        $metrics = array_keys(self::TASK_STATUS_METRICS);
        $rows    = $this->fetchSnapshots($userId, $timeRange, $metrics);
        $labels  = $this->buildLabels($timeRange);

        $series = [];
        $colors = [];

        foreach (self::TASK_STATUS_METRICS as $metric => $meta) {
            $series[] = [
                'name' => $meta['name'],
                'data' => $this->buildSeriesData($labels, $rows, $metric),
            ];
            $colors[] = $meta['color'];
        }

        return $this->trimToDataRange(new TimeSeriesChartData(labels: $labels, series: $series, colors: $colors));
    }

    /**
     * Return a time-series chart showing daily task activity (created vs. completed) over the given date range.
     *
     * Values are daily deltas derived from the absolute snapshot totals:
     * - Created: tasks_total[day] - tasks_total[day-1]
     * - Completed: tasks_status_done[day] - tasks_status_done[day-1]
     * Negative deltas are clamped to zero.
     *
     * @param int    $userId    The authenticated user whose snapshots to query.
     * @param string $timeRange One of '7d', '30d', or '90d'.
     * @return TimeSeriesChartData
     */
    public function taskActivity(int $userId, string $timeRange): TimeSeriesChartData
    {
        $metrics = ['tasks_total', 'tasks_status_done'];
        $rows    = $this->fetchSnapshots($userId, $timeRange, $metrics);
        $labels  = $this->buildLabels($timeRange);

        $totals    = $this->buildSeriesData($labels, $rows, 'tasks_total');
        $doneTotal = $this->buildSeriesData($labels, $rows, 'tasks_status_done');

        $created   = $this->computeDeltas($totals);
        $completed = $this->computeDeltas($doneTotal);

        return $this->trimToDataRange(new TimeSeriesChartData(
            labels: $labels,
            series: [
                ['name' => 'Created',   'data' => $created],
                ['name' => 'Completed', 'data' => $completed],
            ],
            colors: ['#3b82f6', '#22c55e'],
        ));
    }

    /**
     * Return a time-series chart showing each follow-up status count over the given date range.
     *
     * Series: Open, Snoozed, Done.
     *
     * @param int    $userId    The authenticated user whose snapshots to query.
     * @param string $timeRange One of '7d', '30d', or '90d'.
     * @return TimeSeriesChartData
     */
    public function followUpsOverTime(int $userId, string $timeRange): TimeSeriesChartData
    {
        $metrics = array_keys(self::FOLLOW_UP_STATUS_METRICS);
        $rows    = $this->fetchSnapshots($userId, $timeRange, $metrics);
        $labels  = $this->buildLabels($timeRange);

        $series = [];
        $colors = [];

        foreach (self::FOLLOW_UP_STATUS_METRICS as $metric => $meta) {
            $series[] = [
                'name' => $meta['name'],
                'data' => $this->buildSeriesData($labels, $rows, $metric),
            ];
            $colors[] = $meta['color'];
        }

        return $this->trimToDataRange(new TimeSeriesChartData(labels: $labels, series: $series, colors: $colors));
    }

    /**
     * Query the analytics_snapshots table for a set of metrics within the date range.
     *
     * Returns a collection keyed by "metric|date" for O(1) lookups.
     *
     * @param int             $userId    User to filter by.
     * @param string          $timeRange One of '7d', '30d', or '90d'.
     * @param list<string>    $metrics   Metric column values to include.
     * @return Collection<string, int>
     */
    private function fetchSnapshots(int $userId, string $timeRange, array $metrics): Collection
    {
        [$startDate, $endDate] = $this->resolveDateRange($timeRange);

        return AnalyticsSnapshot::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereBetween('snapshot_date', [$startDate, $endDate])
            ->whereIn('metric', $metrics)
            ->get(['metric', 'snapshot_date', 'value'])
            ->keyBy(fn ($row): string => $row->metric . '|' . $row->snapshot_date->toDateString());
    }

    /**
     * Build a sequential list of ISO date strings covering the time range up to and including today.
     *
     * @param string $timeRange One of '7d', '30d', or '90d'.
     * @return list<string>
     */
    private function buildLabels(string $timeRange): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($timeRange);

        $labels  = [];
        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);

        while ($current->lte($end)) {
            $labels[] = $current->toDateString();
            $current->addDay();
        }

        return $labels;
    }

    /**
     * Build a data array for one metric aligned to the given labels, defaulting to zero when no row exists.
     *
     * @param list<string>              $labels ISO date labels.
     * @param Collection<string, mixed> $rows   Snapshot rows keyed by "metric|date".
     * @param string                    $metric The metric to extract.
     * @return list<int>
     */
    private function buildSeriesData(array $labels, Collection $rows, string $metric): array
    {
        $data = [];

        foreach ($labels as $date) {
            $key    = $metric . '|' . $date;
            $data[] = isset($rows[$key]) ? (int) $rows[$key]->value : 0;
        }

        return $data;
    }

    /**
     * Convert an array of absolute daily totals into daily deltas, clamped at zero.
     *
     * The first day always has a delta of zero because there is no prior day in the range.
     *
     * @param list<int> $totals Absolute values per day, indexed by day position.
     * @return list<int>
     */
    private function computeDeltas(array $totals): array
    {
        if (empty($totals)) {
            return [];
        }

        $deltas = [0];

        for ($i = 1; $i < count($totals); $i++) {
            $deltas[] = max(0, $totals[$i] - $totals[$i - 1]);
        }

        return $deltas;
    }

    /**
     * Trim a TimeSeriesChartData to only include dates that have at least one non-zero value across all series.
     *
     * Returns the original data unchanged if no data exists or if all dates have data.
     *
     * @param TimeSeriesChartData $data The full-range chart data.
     * @return TimeSeriesChartData Trimmed chart data covering only dates with actual values.
     */
    private function trimToDataRange(TimeSeriesChartData $data): TimeSeriesChartData
    {
        if (empty($data->labels) || empty($data->series)) {
            return $data;
        }

        $firstIndex = null;
        $lastIndex  = null;
        $labelCount = count($data->labels);

        for ($i = 0; $i < $labelCount; $i++) {
            foreach ($data->series as $series) {
                if (($series['data'][$i] ?? 0) !== 0) {
                    $firstIndex ??= $i;
                    $lastIndex = $i;
                }
            }
        }

        if ($firstIndex === null) {
            return $data;
        }

        $startIndex = max(0, $firstIndex - 1);

        if ($startIndex === 0 && $lastIndex === $labelCount - 1) {
            return $data;
        }

        $length = $lastIndex - $startIndex + 1;
        $labels = array_slice($data->labels, $startIndex, $length);

        $series = array_map(
            fn (array $s): array => [
                'name' => $s['name'],
                'data' => array_values(array_slice($s['data'], $startIndex, $length)),
            ],
            $data->series,
        );

        return new TimeSeriesChartData(labels: $labels, series: $series, colors: $data->colors);
    }

    /**
     * Resolve the start and end date strings for the given time range key.
     *
     * @param string $timeRange One of '7d', '30d', or '90d'.
     * @return array{0: string, 1: string} Start date and end date as Y-m-d strings.
     */
    private function resolveDateRange(string $timeRange): array
    {
        $days      = self::TIME_RANGE_DAYS[$timeRange] ?? self::TIME_RANGE_DAYS['30d'];
        $endDate   = Carbon::today()->toDateString();
        $startDate = Carbon::today()->subDays($days - 1)->toDateString();

        return [$startDate, $endDate];
    }
}
