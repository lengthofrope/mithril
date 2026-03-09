<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating and updating tasks.
 */
class TaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert empty strings to null for nullable foreign key fields.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $nullableFields = ['team_id', 'team_member_id', 'task_group_id', 'task_category_id'];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Get the validation rules for task creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('PATCH') || $this->isMethod('PUT') ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'deadline' => ['nullable', 'date'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'team_member_id' => ['nullable', 'integer', 'exists:team_members,id'],
            'task_group_id' => ['nullable', 'integer', 'exists:task_groups,id'],
            'task_category_id' => ['nullable', 'integer', 'exists:task_categories,id'],
            'is_private' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
