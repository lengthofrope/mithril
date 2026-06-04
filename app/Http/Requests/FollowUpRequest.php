<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating and updating follow-ups.
 */
class FollowUpRequest extends FormRequest
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
        $nullableFields = ['task_id', 'team_member_id'];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Get the validation rules for follow-up creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('user_id', auth()->id())],
            'team_member_id' => ['nullable', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'title' => [$this->isMethod('PATCH') || $this->isMethod('PUT') ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'is_private' => ['boolean'],
            'waiting_on' => ['nullable', 'string', 'max:255'],
            'follow_up_date' => ['nullable', 'date'],
            'snoozed_until' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(FollowUpStatus::class)],
        ];
    }
}
