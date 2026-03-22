<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MeetingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating and updating meetings.
 */
class MeetingRequest extends FormRequest
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
     * Get the validation rules for meeting creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MeetingType::class)],
            'scheduled_at' => ['required', 'date'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('user_id', auth()->id())],
            'notes' => ['nullable', 'string'],
            'transcription_language' => ['sometimes', 'string', 'in:nl,en'],
            'output_language' => ['sometimes', 'nullable', 'string', 'in:nl,en'],
        ];
    }
}
