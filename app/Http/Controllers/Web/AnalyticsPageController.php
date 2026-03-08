<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ChartType;
use App\Enums\DataSource;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AnalyticsWidget;
use App\Services\AnalyticsDataService;
use App\Services\AnalyticsSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles the analytics board page, widget configuration, and chart data API.
 *
 * The index action renders the full analytics page. The remaining actions expose
 * a JSON API for widget CRUD and on-demand chart data fetching, all consumed via
 * the front-end Alpine.js components.
 */
class AnalyticsPageController extends Controller
{
    use ApiResponse;

    /**
     * Display the analytics board page.
     *
     * @return View
     */
    public function index(): View
    {
        return view('pages.analytics', [
            'title'       => 'Analytics',
            'widgets'     => AnalyticsWidget::forAnalytics()->get(),
            'dataSources' => DataSource::cases(),
            'chartTypes'  => ChartType::cases(),
        ]);
    }

    /**
     * Return aggregated chart data for one or more data sources.
     *
     * Accepts a `sources[]` query parameter containing valid DataSource values.
     * Time-series sources additionally accept a `time_range` parameter.
     * Each resolved source is keyed by its string value in the response payload.
     *
     * @param Request                  $request
     * @param AnalyticsDataService     $dataService
     * @param AnalyticsSnapshotService $snapshotService
     * @return JsonResponse
     */
    public function widgetData(
        Request $request,
        AnalyticsDataService $dataService,
        AnalyticsSnapshotService $snapshotService,
    ): JsonResponse {
        $validSourceValues = array_column(DataSource::cases(), 'value');

        $validated = $request->validate([
            'sources'    => ['required', 'array', 'min:1'],
            'sources.*'  => ['required', 'string', Rule::in($validSourceValues)],
            'time_range' => ['nullable', 'string', Rule::in(['7d', '30d', '90d'])],
        ]);

        $timeRange = $validated['time_range'] ?? '30d';
        $userId    = $request->user()->id;
        $result    = [];

        foreach ($validated['sources'] as $sourceKey) {
            $source = DataSource::from($sourceKey);

            if ($source->isTimeSeries()) {
                $chartData = $this->resolveTimeSeries($snapshotService, $source, $userId, $timeRange);
            } else {
                $chartData = $dataService->resolve($source);
            }

            $result[$sourceKey] = [
                'labels' => $chartData->labels,
                'series' => $chartData->series,
                'colors' => $chartData->colors,
            ];
        }

        return $this->successResponse(['sources' => $result]);
    }

    /**
     * Store a new analytics widget for the authenticated user.
     *
     * Auto-assigns sort positions for both the analytics and dashboard contexts
     * based on the current maximum values for the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validSourceValues    = array_column(DataSource::cases(), 'value');
        $validChartTypeValues = array_column(ChartType::cases(), 'value');

        $validated = $request->validate([
            'data_source'        => ['required', 'string', Rule::in($validSourceValues)],
            'chart_type'         => ['required', 'string', Rule::in($validChartTypeValues)],
            'title'              => ['nullable', 'string', 'max:100'],
            'column_span'        => ['required', 'integer', 'between:1,3'],
            'show_on_analytics'  => ['boolean'],
            'show_on_dashboard'  => ['boolean'],
            'time_range'         => ['nullable', 'string', Rule::in(['7d', '30d', '90d'])],
        ]);

        $nextAnalyticsOrder = (AnalyticsWidget::query()->max('sort_order_analytics') ?? 0) + 1;
        $nextDashboardOrder = (AnalyticsWidget::query()->max('sort_order_dashboard') ?? 0) + 1;

        $widget = AnalyticsWidget::create([
            ...$validated,
            'user_id'              => $request->user()->id,
            'sort_order_analytics' => $nextAnalyticsOrder,
            'sort_order_dashboard' => $nextDashboardOrder,
        ]);

        return $this->successResponse($widget, statusCode: 201, includeSavedAt: true);
    }

    /**
     * Update an existing analytics widget.
     *
     * All fields are optional; only supplied values are validated and persisted.
     *
     * @param Request         $request
     * @param AnalyticsWidget $analyticsWidget
     * @return JsonResponse
     */
    public function update(Request $request, AnalyticsWidget $analyticsWidget): JsonResponse
    {
        $validSourceValues    = array_column(DataSource::cases(), 'value');
        $validChartTypeValues = array_column(ChartType::cases(), 'value');

        $validated = $request->validate([
            'data_source'        => ['sometimes', 'string', Rule::in($validSourceValues)],
            'chart_type'         => ['sometimes', 'string', Rule::in($validChartTypeValues)],
            'title'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'column_span'        => ['sometimes', 'integer', 'between:1,3'],
            'show_on_analytics'  => ['sometimes', 'boolean'],
            'show_on_dashboard'  => ['sometimes', 'boolean'],
            'time_range'         => ['sometimes', 'nullable', 'string', Rule::in(['7d', '30d', '90d'])],
        ]);

        $analyticsWidget->update($validated);

        return $this->successResponse($analyticsWidget, includeSavedAt: true);
    }

    /**
     * Delete an analytics widget.
     *
     * @param AnalyticsWidget $analyticsWidget
     * @return JsonResponse
     */
    public function destroy(AnalyticsWidget $analyticsWidget): JsonResponse
    {
        $analyticsWidget->delete();

        return $this->successResponse(null);
    }

    /**
     * Resolve a time-series data source to its chart data via the snapshot service.
     *
     * @param AnalyticsSnapshotService $service
     * @param DataSource               $source
     * @param int                      $userId
     * @param string                   $timeRange
     * @return \App\DataTransferObjects\TimeSeriesChartData
     */
    private function resolveTimeSeries(
        AnalyticsSnapshotService $service,
        DataSource $source,
        int $userId,
        string $timeRange,
    ): \App\DataTransferObjects\TimeSeriesChartData {
        return match ($source) {
            DataSource::TasksOverTime     => $service->tasksOverTime($userId, $timeRange),
            DataSource::TaskActivity      => $service->taskActivity($userId, $timeRange),
            DataSource::FollowUpsOverTime => $service->followUpsOverTime($userId, $timeRange),
        };
    }
}
