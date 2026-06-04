<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutoSaveRequest;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        'meeting' => \App\Models\Meeting::class,
        'meeting_prep_item' => \App\Models\MeetingPrepItem::class,
        'agreement' => \App\Models\Agreement::class,
        'note' => \App\Models\Note::class,
        'weekly_reflection' => \App\Models\WeeklyReflection::class,
        'jira_issue' => \App\Models\JiraIssue::class,
        'user' => \App\Models\User::class,
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

        if ($modelKey === 'user') {
            abort_if((int) $id !== (int) $request->user()->getKey(), 403);
        }

        $model = $modelClass::findOrFail($id);

        $blockedFields = ['id', 'user_id', 'created_at', 'updated_at', 'recurrence_parent_id', 'recurrence_series_id'];

        if (in_array($field, $blockedFields, true)) {
            return $this->errorResponse("Field '{$field}' cannot be auto-saved.", [], 422);
        }

        if (!in_array($field, $model->getFillable(), true)) {
            return $this->errorResponse("Field '{$field}' is not fillable on {$modelKey}.", [], 422);
        }

        $fieldRules = $this->getFieldValidationRules($modelKey, $field, $model);

        if (!empty($fieldRules)) {
            $fieldValidator = Validator::make(
                ['value' => $value],
                ['value' => $fieldRules],
            );

            if ($fieldValidator->fails()) {
                return $this->errorResponse(
                    $fieldValidator->errors()->first('value'),
                    $fieldValidator->errors()->toArray(),
                    422,
                );
            }
        }

        $model->update([$field => $value]);

        return $this->successResponse($model->fresh(), 'Saved.', 200, true);
    }

    /**
     * Return validation rules for a specific model and field combination.
     *
     * @param string $modelKey
     * @param string $field
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array<int, mixed>
     */
    private function getFieldValidationRules(string $modelKey, string $field, \Illuminate\Database\Eloquent\Model $model): array
    {
        $rulesMap = [
            'task_category' => [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('task_categories', 'name')
                        ->where('user_id', $model->getAttribute('user_id'))
                        ->ignore($model->getKey()),
                ],
            ],
            'task_group' => [
                'name' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ],
            'user' => [
                'activity_sort_order' => ['required', 'string', Rule::in(['asc', 'desc'])],
            ],
            'follow_up' => [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'priority' => ['required', Rule::enum(Priority::class)],
                'is_private' => ['boolean'],
            ],
        ];

        return $rulesMap[$modelKey][$field] ?? [];
    }
}
