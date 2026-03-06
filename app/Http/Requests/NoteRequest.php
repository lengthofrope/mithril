<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating and updating notes.
 */
class NoteRequest extends FormRequest
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
     * Get the validation rules for note creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'team_member_id' => ['nullable', 'integer', 'exists:team_members,id'],
            'is_pinned' => ['boolean'],
        ];
    }
}
