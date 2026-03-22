<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\DiarizeMeetingJob;
use App\Jobs\TranscribeMeetingJob;
use Illuminate\Support\Facades\Bus;
use App\Models\Attachment;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles audio recording upload, storage, and deletion for meetings.
 */
class MeetingRecordingController extends Controller
{
    use ApiResponse;

    /**
     * Store a new recording for a meeting (browser recording or file upload).
     *
     * Validates file size against the configured limit and the user's storage quota.
     * Optionally transitions the meeting to in_progress.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        if (!config('meetings.recording.enabled', true)) {
            return $this->errorResponse('Recording feature is disabled.', statusCode: 403);
        }

        $maxMb = config('meetings.recording.max_upload_mb', 500);
        $allowedMimes = implode(',', config('meetings.recording.allowed_mime_types', []));

        $request->validate([
            'audio' => ['required', 'file', "max:{$this->mbToKb($maxMb)}", "mimetypes:{$allowedMimes}"],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = $request->file('audio');
        $incomingSize = $file->getSize();

        $quotaCheck = $this->checkStorageQuota($incomingSize);
        if ($quotaCheck !== null) {
            return $quotaCheck;
        }

        $disk = config('meetings.recording.disk', 'local');
        $directory = 'recordings/' . now()->format('Y/m');
        $extension = $file->guessExtension() ?? 'webm';
        $uniqueName = uniqid('rec_', true) . '.' . $extension;
        $path = $file->storeAs($directory, $uniqueName, $disk);

        $recording = MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size_bytes' => $incomingSize,
            'duration_seconds' => $request->input('duration_seconds'),
        ]);

        if (config('meetings.recording.auto_start_meeting', true)
            && $meeting->status === MeetingStatus::Scheduled
        ) {
            $meeting->update([
                'status' => MeetingStatus::InProgress,
                'started_at' => $meeting->started_at ?? now(),
            ]);
        }

        if (config('meetings.transcription.enabled', true) && config('meetings.transcription.auto_start', true)) {
            if (config('meetings.diarization.enabled', false)) {
                Bus::chain([
                    new TranscribeMeetingJob($meeting, $recording),
                    new DiarizeMeetingJob($meeting, $recording),
                ])->dispatch();
            } else {
                TranscribeMeetingJob::dispatch($meeting, $recording);
            }
        }

        return $this->successResponse($recording, 'Recording saved.', 201);
    }

    /**
     * Stream the audio file for playback.
     *
     * @param Meeting $meeting
     * @param MeetingRecording $recording
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function stream(Meeting $meeting, MeetingRecording $recording): \Symfony\Component\HttpFoundation\Response
    {
        if ($recording->meeting_id !== $meeting->id) {
            abort(404);
        }

        return Storage::disk($recording->disk)->response(
            $recording->path,
            $recording->original_filename ?? 'recording',
            ['Content-Type' => $recording->mime_type],
        );
    }

    /**
     * Delete a recording and its file from disk.
     *
     * @param Meeting $meeting
     * @param MeetingRecording $recording
     * @return JsonResponse
     */
    public function destroy(Meeting $meeting, MeetingRecording $recording): JsonResponse
    {
        if ($recording->meeting_id !== $meeting->id) {
            return $this->errorResponse('Recording does not belong to this meeting.', statusCode: 404);
        }

        $recording->deleteFile();
        $recording->delete();

        return $this->successResponse(null, 'Recording deleted.');
    }

    /**
     * Check whether the incoming file fits within the user's storage quota.
     *
     * @param int $incomingSize File size in bytes.
     * @return JsonResponse|null Error response if over quota, null if within.
     */
    private function checkStorageQuota(int $incomingSize): ?JsonResponse
    {
        $maxBytes = config('attachments.max_storage_mb') * 1024 * 1024;

        $attachmentUsage = Attachment::where('user_id', auth()->id())->sum('size');
        $recordingUsage = MeetingRecording::where('user_id', auth()->id())->sum('size_bytes');

        $totalUsage = $attachmentUsage + $recordingUsage;

        if (($totalUsage + $incomingSize) > $maxBytes) {
            return $this->errorResponse('Storage quota exceeded.', [], 422);
        }

        return null;
    }

    /**
     * Convert megabytes to kilobytes for Laravel's max validation rule.
     *
     * @param int $mb
     * @return int
     */
    private function mbToKb(int $mb): int
    {
        return $mb * 1024;
    }
}
