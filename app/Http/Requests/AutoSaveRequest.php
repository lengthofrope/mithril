<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for auto-saving a single field on any model.
 *
 * Validates only the envelope (model, id, field, value).
 * Field-level validation is intentionally minimal to support partial updates.
 */
class AutoSaveRequest extends FormRequest
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
     * Get the validation rules for the auto-save envelope.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'alpha_dash'],
            'id' => ['required', 'integer', 'min:1'],
            'field' => ['required', 'string', 'alpha_dash'],
            'value' => ['present'],
        ];
    }
}
