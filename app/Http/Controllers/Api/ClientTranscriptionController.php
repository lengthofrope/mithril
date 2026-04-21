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
use Illuminate\Http\Request;

/**
 * Accepts transcription and diarization results submitted by the browser
 * when the user is in local speech service mode.
 */
class ClientTranscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Mark a meeting transcription as processing before audio is sent to the local speech service.
     *
     * Creates a new MeetingTranscription with status processing when none exists, or updates
     * an existing pending/failed transcription to processing. Idempotent for already-processing
     * transcriptions to handle concurrent tab scenarios.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function startProcessing(Request $request, Meeting $meeting): JsonResponse
    {
        if (! $request->user()->isLocalSpeechMode()) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        if ($meeting->recordings()->doesntExist()) {
            return $this->errorResponse('Meeting has no recordings.', [], 422);
        }

        $transcription = $meeting->transcription;

        if ($transcription !== null && in_array($transcription->status, [
            TranscriptionStatus::Processing,
            TranscriptionStatus::Completed,
        ], true)) {
            return $this->successResponse(null, 'Processing already in progress.');
        }

        if ($transcription !== null) {
            $transcription->update([
                'status' => TranscriptionStatus::Processing,
                'processing_started_at' => now(),
            ]);
        } else {
            MeetingTranscription::forceCreate([
                'user_id' => $meeting->user_id,
                'meeting_id' => $meeting->id,
                'status' => TranscriptionStatus::Processing,
                'processing_started_at' => now(),
            ]);
        }

        return $this->successResponse(null, 'Processing started.');
    }

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
        $data['diarized_content'] = $diarizedContent;
        $data['diarization_status'] = $diarizedContent !== null ? DiarizationStatus::Completed : null;
        $data['diarization_error'] = null;

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
