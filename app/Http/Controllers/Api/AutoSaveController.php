<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutoSaveRequest;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Generic controller for auto-saving individual fields on any model.
 *
 * Accepts { model: "task", id: 5, field: "title", value: "..." }
 * and performs a partial update on the specified record.
 */
class AutoSaveController extends Controller
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
        'follow_up' => \App\Models\FollowUp::class,
        'bila' => \App\Models\Bila::class,
        'bila_prep_item' => \App\Models\BilaPrepItem::class,
        'agreement' => \App\Models\Agreement::class,
        'note' => \App\Models\Note::class,
        'weekly_reflection' => \App\Models\WeeklyReflection::class,
        'jira_issue' => \App\Models\JiraIssue::class,
    ];

    /**
     * Perform a partial update (single field auto-save) on the specified model.
     *
     * @param AutoSaveRequest $request
     * @return JsonResponse
     */
    public function __invoke(AutoSaveRequest $request): JsonResponse
    {
        $modelKey = $request->validated('model');
        $id = $request->validated('id');
        $field = $request->validated('field');
        $value = $request->validated('value');

        if (!isset($this->modelMap[$modelKey])) {
            return $this->errorResponse("Unknown model: {$modelKey}", [], 422);
        }

        $modelClass = $this->modelMap[$modelKey];
        $model = $modelClass::findOrFail($id);

        $blockedFields = ['id', 'user_id', 'created_at', 'updated_at', 'recurrence_parent_id', 'recurrence_series_id'];

        if (in_array($field, $blockedFields, true)) {
            return $this->errorResponse("Field '{$field}' cannot be auto-saved.", [], 422);
        }

        if (!in_array($field, $model->getFillable(), true)) {
            return $this->errorResponse("Field '{$field}' is not fillable on {$modelKey}.", [], 422);
        }

        $model->update([$field => $value]);

        return $this->successResponse($model->fresh(), 'Saved.', 200, true);
    }
}
