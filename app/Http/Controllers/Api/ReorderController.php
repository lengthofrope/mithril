<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRequest;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Generic controller for reordering any model that uses the HasSortOrder trait.
 *
 * Accepts { model_type: "task", items: [{id: int, sort_order: int}] }
 * and delegates to the model's static reorder() method.
 */
class ReorderController extends Controller
{
    use ApiResponse;

    /**
     * Map of model identifiers to their fully qualified class names.
     *
     * @var array<string, class-string>
     */
    private array $modelMap = [
        'task'             => \App\Models\Task::class,
        'task_group'       => \App\Models\TaskGroup::class,
        'task_category'    => \App\Models\TaskCategory::class,
        'team'             => \App\Models\Team::class,
        'team_member'      => \App\Models\TeamMember::class,
        'bila_prep_item'   => \App\Models\BilaPrepItem::class,
        'analytics_widget' => \App\Models\AnalyticsWidget::class,
    ];

    /**
     * Sort fields that carry context information for AnalyticsWidget reordering.
     *
     * @var array<string, string>
     */
    private array $analyticsContextMap = [
        'sort_order_analytics' => 'analytics',
        'sort_order_dashboard' => 'dashboard',
    ];

    /**
     * Reorder the specified model records.
     *
     * For the analytics_widget model a sort_field must be supplied to identify
     * which context (analytics or dashboard) is being reordered. All other models
     * delegate to their own static reorder() method from the HasSortOrder trait.
     *
     * @param ReorderRequest $request
     * @return JsonResponse
     */
    public function __invoke(ReorderRequest $request): JsonResponse
    {
        $modelKey  = $request->validated('model_type');
        $items     = $request->validated('items');
        $sortField = $request->validated('sort_field');

        if (!isset($this->modelMap[$modelKey])) {
            return $this->errorResponse("Unknown model: {$modelKey}", [], 422);
        }

        $modelClass = $this->modelMap[$modelKey];

        if ($modelKey === 'analytics_widget') {
            return $this->reorderAnalyticsWidget($items, $sortField);
        }

        if (!method_exists($modelClass, 'reorder')) {
            return $this->errorResponse("Model {$modelKey} does not support reordering.", [], 422);
        }

        $modelClass::reorder($items);

        return $this->successResponse(null, 'Reordered successfully.');
    }

    /**
     * Delegate analytics widget reordering to the context-aware model method.
     *
     * @param array<int, array{id: int, sort_order: int}> $items
     * @param string|null                                  $sortField
     * @return JsonResponse
     */
    private function reorderAnalyticsWidget(array $items, ?string $sortField): JsonResponse
    {
        if ($sortField === null || !isset($this->analyticsContextMap[$sortField])) {
            return $this->errorResponse(
                'A valid sort_field is required for analytics_widget reordering. '
                . 'Accepted values: ' . implode(', ', array_keys($this->analyticsContextMap)) . '.',
                [],
                422,
            );
        }

        $context = $this->analyticsContextMap[$sortField];

        \App\Models\AnalyticsWidget::reorderForContext($items, $context);

        return $this->successResponse(null, 'Reordered successfully.');
    }
}
