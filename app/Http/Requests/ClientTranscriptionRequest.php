<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for client-submitted transcription results from local speech service.
 */
class ClientTranscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->isLocalSpeechMode();
    }

    /**
     * Get the validation rules for client transcription submission.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1'],
            'diarized_content' => ['nullable', 'string'],
            'language' => ['required', 'string', 'in:nl,en'],
        ];
    }
}
