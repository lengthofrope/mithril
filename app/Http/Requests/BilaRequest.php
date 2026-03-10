<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating and updating bilas (1-on-1 meetings).
 */
class BilaRequest extends FormRequest
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
     * Get the validation rules for bila creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_member_id' => ['required', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'scheduled_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
