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
        'task' => \App\Models\Task::class,
        'task_group' => \App\Models\TaskGroup::class,
        'task_category' => \App\Models\TaskCategory::class,
        'team' => \App\Models\Team::class,
        'team_member' => \App\Models\TeamMember::class,
        'bila_prep_item' => \App\Models\BilaPrepItem::class,
    ];

    /**
     * Reorder the specified model records.
     *
     * @param ReorderRequest $request
     * @return JsonResponse
     */
    public function __invoke(ReorderRequest $request): JsonResponse
    {
        $modelKey = $request->validated('model_type');
        $items = $request->validated('items');

        if (!isset($this->modelMap[$modelKey])) {
            return $this->errorResponse("Unknown model: {$modelKey}", [], 422);
        }

        $modelClass = $this->modelMap[$modelKey];

        if (!method_exists($modelClass, 'reorder')) {
            return $this->errorResponse("Model {$modelKey} does not support reordering.", [], 422);
        }

        $modelClass::reorder($items);

        return $this->successResponse(null, 'Reordered successfully.');
    }
}
