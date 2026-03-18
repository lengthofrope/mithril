<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TranscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for meeting transcription operations.
 *
 * Provides status polling, retry, and manual transcription input.
 */
class MeetingTranscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Get the transcription status and content for a meeting.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $transcription = $meeting->transcription;

        if ($transcription === null) {
            return $this->successResponse([
                'status' => null,
                'content' => null,
            ]);
        }

        return $this->successResponse([
            'status' => $transcription->status->value,
            'content' => $transcription->content,
            'language' => $transcription->language,
            'provider' => $transcription->provider,
            'error_message' => $transcription->error_message,
            'updated_at' => $transcription->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Retry a failed transcription by re-dispatching the job.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function retry(Meeting $meeting): JsonResponse
    {
        $recording = $meeting->recordings()->latest()->first();

        if ($recording === null) {
            return $this->errorResponse('No recording available for transcription.', statusCode: 422);
        }

        $transcription = $meeting->transcription;

        if ($transcription !== null && $transcription->status === TranscriptionStatus::Processing) {
            return $this->errorResponse('Transcription is already in progress.', statusCode: 422);
        }

        if ($transcription !== null) {
            $transcription->update([
                'status' => TranscriptionStatus::Pending,
                'error_message' => null,
            ]);
        }

        TranscribeMeetingJob::dispatch($meeting, $recording);

        return $this->successResponse(null, 'Transcription job dispatched.');
    }

    /**
     * Save manually entered transcription content.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function storeManual(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'language' => ['sometimes', 'string', 'in:nl,en'],
        ]);

        $transcription = $meeting->transcription;

        if ($transcription !== null) {
            $transcription->update([
                'content' => $validated['content'],
                'language' => $validated['language'] ?? $meeting->transcription_language,
                'provider' => 'manual',
                'status' => TranscriptionStatus::Completed,
                'error_message' => null,
            ]);
        } else {
            MeetingTranscription::forceCreate([
                'user_id' => $meeting->user_id,
                'meeting_id' => $meeting->id,
                'content' => $validated['content'],
                'language' => $validated['language'] ?? $meeting->transcription_language,
                'provider' => 'manual',
                'status' => TranscriptionStatus::Completed,
            ]);
        }

        return $this->successResponse(null, 'Transcription saved.', 200, true);
    }
}
