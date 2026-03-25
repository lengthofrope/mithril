<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DiarizationStatus;
use App\Enums\TranscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientTranscriptionRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use Illuminate\Http\JsonResponse;

/**
 * Accepts transcription and diarization results submitted by the browser
 * when the user is in local speech service mode.
 */
class ClientTranscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Store a transcription result submitted from the browser's local speech service.
     *
     * @param ClientTranscriptionRequest $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function storeResult(ClientTranscriptionRequest $request, Meeting $meeting): JsonResponse
    {
        $transcription = $meeting->transcription;

        $data = [
            'content' => $request->validated('content'),
            'language' => $request->validated('language'),
            'provider' => 'unified',
            'status' => TranscriptionStatus::Completed,
            'error_message' => null,
        ];

        $diarizedContent = $request->validated('diarized_content');

        if ($diarizedContent !== null) {
            $data['diarized_content'] = $diarizedContent;
            $data['diarization_status'] = DiarizationStatus::Completed;
            $data['diarization_error'] = null;
        }

        if ($transcription !== null) {
            $transcription->update($data);
        } else {
            MeetingTranscription::forceCreate(array_merge($data, [
                'user_id' => $meeting->user_id,
                'meeting_id' => $meeting->id,
            ]));
        }

        return $this->successResponse(null, 'Transcription saved.', 200, true);
    }
}
