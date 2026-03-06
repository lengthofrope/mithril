<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating and updating agreements.
 */
class AgreementRequest extends FormRequest
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
     * Get the validation rules for agreement creation and update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'description' => ['required', 'string'],
            'agreed_date' => ['required', 'date'],
            'follow_up_date' => ['nullable', 'date'],
        ];
    }
}
