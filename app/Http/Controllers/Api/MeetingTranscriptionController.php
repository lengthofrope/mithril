<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DiarizationStatus;
use App\Enums\TranscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\DiarizeMeetingJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use App\Models\ProcessingTimingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

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
            'diarized_content' => $transcription->diarized_content,
            'diarization_status' => $transcription->diarization_status?->value,
            'diarization_error' => $transcription->diarization_error,
            'language' => $transcription->language,
            'provider' => $transcription->provider,
            'error_message' => $transcription->error_message,
            'updated_at' => $transcription->updated_at->toIso8601String(),
            'processing_started_at' => $transcription->processing_started_at?->toIso8601String(),
            'audio_duration_seconds' => $transcription->audio_duration_seconds,
            'estimated_duration_seconds' => $this->estimateProcessingDuration($transcription),
            'diarization_started_at' => $transcription->diarization_started_at?->toIso8601String(),
            'estimated_diarization_seconds' => $this->estimateDiarizationDuration($transcription),
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

        if (config('meetings.diarization.enabled', false)) {
            Bus::chain([
                new TranscribeMeetingJob($meeting, $recording),
                new DiarizeMeetingJob($meeting, $recording),
            ])->dispatch();
        } else {
            TranscribeMeetingJob::dispatch($meeting, $recording);
        }

        return $this->successResponse(null, 'Transcription job dispatched.');
    }

    /**
     * Reset and retranscribe all recordings for a meeting from scratch.
     *
     * Clears existing transcription content and dispatches a chained job
     * for each recording in chronological order.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function retranscribe(Meeting $meeting): JsonResponse
    {
        $recordings = $meeting->recordings()->oldest()->get();

        if ($recordings->isEmpty()) {
            return $this->errorResponse('No recordings available for transcription.', statusCode: 422);
        }

        $transcription = $meeting->transcription;

        if ($transcription !== null && $transcription->status === TranscriptionStatus::Processing) {
            return $this->errorResponse('Transcription is already in progress.', statusCode: 422);
        }

        if ($transcription !== null) {
            $transcription->update([
                'content' => null,
                'status' => TranscriptionStatus::Pending,
                'error_message' => null,
            ]);
        }

        $jobs = $recordings->map(
            fn ($recording) => new TranscribeMeetingJob($meeting, $recording)
        )->all();

        if (config('meetings.diarization.enabled', false)) {
            $lastRecording = $recordings->last();
            $jobs[] = new DiarizeMeetingJob($meeting, $lastRecording);
        }

        Bus::chain($jobs)->dispatch();

        return $this->successResponse(null, 'Retranscription jobs dispatched.');
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

    /**
     * Trigger speaker diarization for a meeting's transcription.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function diarize(Meeting $meeting): JsonResponse
    {
        $recording = $meeting->recordings()->latest()->first();

        if ($recording === null) {
            return $this->errorResponse('No recording available for diarization.', statusCode: 422);
        }

        $transcription = $meeting->transcription;

        if ($transcription === null || $transcription->status !== TranscriptionStatus::Completed) {
            return $this->errorResponse('Transcription must be completed before diarization.', statusCode: 422);
        }

        if ($transcription->diarization_status === DiarizationStatus::Processing) {
            return $this->errorResponse('Diarization is already in progress.', statusCode: 422);
        }

        $transcription->update([
            'diarization_status' => DiarizationStatus::Pending,
            'diarization_error' => null,
        ]);

        DiarizeMeetingJob::dispatch($meeting, $recording);

        return $this->successResponse(null, 'Diarization job dispatched.');
    }

    /**
     * Retry a failed diarization by re-dispatching the job.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function retryDiarization(Meeting $meeting): JsonResponse
    {
        $recording = $meeting->recordings()->latest()->first();

        if ($recording === null) {
            return $this->errorResponse('No recording available for diarization.', statusCode: 422);
        }

        $transcription = $meeting->transcription;

        if ($transcription === null) {
            return $this->errorResponse('No transcription available.', statusCode: 422);
        }

        if ($transcription->diarization_status === DiarizationStatus::Processing) {
            return $this->errorResponse('Diarization is already in progress.', statusCode: 422);
        }

        $transcription->update([
            'diarization_status' => DiarizationStatus::Pending,
            'diarization_error' => null,
        ]);

        DiarizeMeetingJob::dispatch($meeting, $recording);

        return $this->successResponse(null, 'Diarization retry job dispatched.');
    }

    /**
     * Estimate processing duration based on audio length and historical ratio.
     *
     * @param MeetingTranscription $transcription
     * @return int|null Estimated seconds, or null when no estimate is possible.
     */
    private function estimateProcessingDuration(MeetingTranscription $transcription): ?int
    {
        if ($transcription->audio_duration_seconds === null) {
            return null;
        }

        $averageRatio = ProcessingTimingLog::where('user_id', $transcription->user_id)
            ->where('type', 'transcription')
            ->selectRaw('AVG((processing_duration_seconds * 1.0) / audio_duration_seconds) as ratio')
            ->value('ratio');

        if ($averageRatio === null) {
            return null;
        }

        return (int) round((float) $averageRatio * $transcription->audio_duration_seconds);
    }

    /**
     * Estimate diarization duration based on audio length and historical ratio.
     *
     * @param MeetingTranscription $transcription
     * @return int|null Estimated seconds, or null when no estimate is possible.
     */
    private function estimateDiarizationDuration(MeetingTranscription $transcription): ?int
    {
        if ($transcription->audio_duration_seconds === null) {
            return null;
        }

        $averageRatio = ProcessingTimingLog::where('user_id', $transcription->user_id)
            ->where('type', 'diarization')
            ->selectRaw('AVG((processing_duration_seconds * 1.0) / audio_duration_seconds) as ratio')
            ->value('ratio');

        if ($averageRatio === null) {
            return null;
        }

        return (int) round((float) $averageRatio * $transcription->audio_duration_seconds);
    }
}
