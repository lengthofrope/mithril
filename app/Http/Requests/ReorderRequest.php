<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for reordering records of any model with HasSortOrder.
 */
class ReorderRequest extends FormRequest
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
     * Get the validation rules for the reorder payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model_type'  => ['required', 'string', 'alpha_dash'],
            'items'       => ['required', 'array', 'min:1'],
            'items.*.id'  => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
            'sort_field'  => ['nullable', 'string', 'in:sort_order,sort_order_analytics,sort_order_dashboard'],
        ];
    }
}
