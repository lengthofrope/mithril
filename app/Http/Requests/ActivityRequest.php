<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating and updating activity feed entries.
 */
class ActivityRequest extends FormRequest
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
     * Get the validation rules for activity creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type'       => ['required', 'string', 'in:comment,link,attachment'],
            'body'       => ['nullable', 'string', 'max:10000'],
            'url'        => ['required_if:type,link', 'nullable', 'url', 'max:2048'],
            'link_title' => ['nullable', 'string', 'max:255'],
            'files'      => ['required_if:type,attachment', 'nullable', 'array', 'max:5'],
            'files.*'    => ['file', 'max:10240'],
        ];
    }
}
