<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Jobs\ExtractMeetingInsightsJob;
use App\Models\Agreement;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles review actions on AI-extracted meeting items.
 *
 * Provides accept (creates real record), reject, modify, bulk actions, and re-extract.
 */
class MeetingExtractionController extends Controller
{
    use ApiResponse;

    /**
     * List all extractions for a meeting.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function index(Meeting $meeting): JsonResponse
    {
        $extractions = $meeting->extractions()
            ->with('assignee')
            ->orderBy('id')
            ->get();

        return $this->successResponse($extractions);
    }

    /**
     * Accept an extraction and create the corresponding resource.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @param MeetingExtraction $extraction
     * @return JsonResponse
     */
    public function accept(Request $request, Meeting $meeting, MeetingExtraction $extraction): JsonResponse
    {
        if ($extraction->meeting_id !== $meeting->id) {
            return $this->errorResponse('Extraction does not belong to this meeting.', statusCode: 404);
        }

        if ($extraction->status !== ExtractionStatus::Pending) {
            return $this->errorResponse('Extraction has already been reviewed.', statusCode: 422);
        }

        $overrides = $request->validate([
            'content' => ['sometimes', 'string', 'max:1000'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:team_members,id'],
            'priority' => ['sometimes', 'nullable', 'string'],
            'deadline' => ['sometimes', 'nullable', 'date'],
        ]);

        $content = $overrides['content'] ?? $extraction->content;
        $assigneeId = $overrides['assignee_id'] ?? $extraction->assignee_id;
        $priority = $overrides['priority'] ?? $extraction->priority;
        $deadline = $overrides['deadline'] ?? $extraction->deadline;

        $hasOverrides = !empty($overrides);
        $newStatus = $hasOverrides ? ExtractionStatus::Modified : ExtractionStatus::Accepted;

        $resource = $this->createResource($extraction->type, $content, $assigneeId, $priority, $deadline, $meeting->id);

        $extraction->update([
            'content' => $content,
            'assignee_id' => $assigneeId,
            'priority' => $priority,
            'deadline' => $deadline,
            'status' => $newStatus,
            'created_model_type' => $resource::class,
            'created_model_id' => $resource->id,
        ]);

        return $this->successResponse([
            'extraction' => $extraction->fresh(),
            'resource' => $resource,
        ], 'Extraction accepted.');
    }

    /**
     * Reject an extraction.
     *
     * @param Meeting $meeting
     * @param MeetingExtraction $extraction
     * @return JsonResponse
     */
    public function reject(Meeting $meeting, MeetingExtraction $extraction): JsonResponse
    {
        if ($extraction->meeting_id !== $meeting->id) {
            return $this->errorResponse('Extraction does not belong to this meeting.', statusCode: 404);
        }

        $extraction->update(['status' => ExtractionStatus::Rejected]);

        return $this->successResponse(null, 'Extraction rejected.');
    }

    /**
     * Bulk accept or reject multiple extractions.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function bulk(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'extraction_ids' => ['required', 'array', 'min:1'],
            'extraction_ids.*' => ['integer'],
        ]);

        $extractions = MeetingExtraction::whereIn('id', $validated['extraction_ids'])
            ->where('meeting_id', $meeting->id)
            ->where('status', ExtractionStatus::Pending)
            ->get();

        $processed = 0;

        foreach ($extractions as $extraction) {
            if ($validated['action'] === 'accept') {
                $resource = $this->createResource(
                    $extraction->type,
                    $extraction->content,
                    $extraction->assignee_id,
                    $extraction->priority,
                    $extraction->deadline,
                    $meeting->id,
                );

                $extraction->update([
                    'status' => ExtractionStatus::Accepted,
                    'created_model_type' => $resource::class,
                    'created_model_id' => $resource->id,
                ]);
            } else {
                $extraction->update(['status' => ExtractionStatus::Rejected]);
            }

            $processed++;
        }

        return $this->successResponse(['processed' => $processed], "Bulk {$validated['action']} completed.");
    }

    /**
     * Re-extract insights by dispatching a new extraction job.
     *
     * Deletes existing pending extractions before re-running.
     *
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function reExtract(Meeting $meeting): JsonResponse
    {
        $transcription = $meeting->transcription;

        if ($transcription === null || $transcription->content === null) {
            return $this->errorResponse('No transcription available for extraction.', statusCode: 422);
        }

        $meeting->extractions()
            ->where('status', ExtractionStatus::Pending)
            ->delete();

        ExtractMeetingInsightsJob::dispatch($meeting);

        return $this->successResponse(null, 'Extraction job dispatched.');
    }

    /**
     * Create the appropriate resource based on extraction type.
     *
     * @param ExtractionType $type
     * @param string $content
     * @param int|null $assigneeId
     * @param string|null $priority
     * @param \Illuminate\Support\Carbon|string|null $deadline
     * @param int $meetingId
     * @return Task|FollowUp|Agreement
     */
    /**
     * Resolve the first attendee of a meeting as a fallback assignee.
     *
     * @param int $meetingId
     * @return int
     */
    private function resolveFirstAttendee(int $meetingId): int
    {
        $meeting = Meeting::withoutGlobalScopes()->findOrFail($meetingId);
        $attendee = $meeting->attendees()->first();

        if ($attendee === null) {
            throw new \RuntimeException('Cannot create agreement without an assignee.');
        }

        return $attendee->id;
    }

    /**
     * Create the appropriate resource based on extraction type.
     *
     * @param ExtractionType $type
     * @param string $content
     * @param int|null $assigneeId
     * @param string|null $priority
     * @param mixed $deadline
     * @param int $meetingId
     * @return Task|FollowUp|Agreement
     */
    private function createResource(
        ExtractionType $type,
        string $content,
        ?int $assigneeId,
        ?string $priority,
        mixed $deadline,
        int $meetingId,
    ): Task|FollowUp|Agreement {
        return match ($type) {
            ExtractionType::Task => Task::create([
                'title' => $content,
                'team_member_id' => $assigneeId,
                'priority' => $priority ?? \App\Enums\Priority::Normal->value,
                'deadline' => $deadline,
                'meeting_id' => $meetingId,
            ]),
            ExtractionType::FollowUp => FollowUp::create([
                'description' => $content,
                'team_member_id' => $assigneeId,
                'follow_up_date' => $deadline ?? now()->addDays(7)->toDateString(),
                'meeting_id' => $meetingId,
            ]),
            ExtractionType::Agreement, ExtractionType::Decision => Agreement::create([
                'description' => $content,
                'team_member_id' => $assigneeId ?? $this->resolveFirstAttendee($meetingId),
                'agreed_date' => now()->toDateString(),
                'meeting_id' => $meetingId,
            ]),
        };
    }
}
