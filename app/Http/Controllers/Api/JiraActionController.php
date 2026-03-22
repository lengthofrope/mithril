<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\MeetingScheduled;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\Note;
use App\Models\Task;
use App\Services\JiraActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles Jira issue action API endpoints: prefill, create resources, and manage links.
 */
class JiraActionController extends Controller
{
    use ApiResponse;

    /**
     * Inject the JiraActionService.
     *
     * @param JiraActionService $service
     */
    public function __construct(private readonly JiraActionService $service) {}

    /**
     * Return pre-fill data for creating a resource from a Jira issue.
     *
     * GET /api/v1/jira-issues/{jiraIssue}/prefill/{type}
     *
     * @param JiraIssue $jiraIssue
     * @param string    $type
     * @return JsonResponse
     */
    public function prefill(JiraIssue $jiraIssue, string $type): JsonResponse
    {
        try {
            $data = $this->service->buildPrefillData($jiraIssue, $type);

            return $this->successResponse($data);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), statusCode: 422);
        }
    }

    /**
     * Create a resource from a Jira issue and link it.
     *
     * POST /api/v1/jira-issues/{jiraIssue}/create/{type}
     *
     * @param Request   $request
     * @param JiraIssue $jiraIssue
     * @param string    $type
     * @return JsonResponse
     */
    public function create(Request $request, JiraIssue $jiraIssue, string $type): JsonResponse
    {
        try {
            $prefill = $this->service->buildPrefillData($jiraIssue, $type);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), statusCode: 422);
        }

        $data = array_merge($prefill, $request->except(['team_member_name']));
        unset($data['team_member_name']);

        $resource = match ($type) {
            'meeting' => $this->createMeeting($jiraIssue, $data),
            'task' => Task::create([
                'title'          => $data['title'],
                'priority'       => $data['priority'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'follow-up' => FollowUp::create([
                'description'    => $data['description'],
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'note' => Note::create([
                'title'          => $data['title'],
                'content'        => $data['content'] ?? '',
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            default => null,
        };

        if ($resource === null) {
            return $this->errorResponse("Invalid resource type: {$type}", statusCode: 400);
        }

        $link = $this->service->linkResource($jiraIssue, $resource);

        return $this->successResponse([
            'resource' => $resource->fresh(),
            'link'     => $link,
        ], 'Created successfully.', 201);
    }

    /**
     * Remove a link between a Jira issue and a resource.
     *
     * DELETE /api/v1/jira-issues/{jiraIssue}/links/{jiraIssueLink}
     *
     * @param JiraIssue     $jiraIssue
     * @param JiraIssueLink $jiraIssueLink
     * @return JsonResponse
     */
    public function unlink(JiraIssue $jiraIssue, JiraIssueLink $jiraIssueLink): JsonResponse
    {
        if ($jiraIssueLink->jira_issue_id !== $jiraIssue->id) {
            return $this->errorResponse('Link does not belong to this issue.', statusCode: 404);
        }

        $jiraIssueLink->delete();

        return $this->successResponse(null, 'Link removed.');
    }

    /**
     * Create a Meeting resource from a Jira issue.
     *
     * If an upcoming meeting exists for the team member, adds a prep item instead.
     *
     * @param JiraIssue            $issue The source Jira issue.
     * @param array<string, mixed> $data  The merged prefill + request data.
     * @return Meeting The created or existing Meeting.
     */
    private function createMeeting(JiraIssue $issue, array $data): Meeting
    {
        $existingMeeting = !empty($data['team_member_id'])
            ? Meeting::query()
                ->whereHas('attendees', fn ($q) => $q->where('team_member_id', $data['team_member_id']))
                ->where('is_done', false)
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->first()
            : null;

        if ($existingMeeting) {
            MeetingPrepItem::create([
                'meeting_id' => $existingMeeting->id,
                'content' => $data['prep_item_content'] ?? $issue->summary,
            ]);

            return $existingMeeting;
        }

        $meeting = Meeting::create([
            'title' => $issue->summary ?? 'Meeting',
            'type' => 'one_on_one',
            'scheduled_at' => now()->addDays(7),
        ]);

        if (!empty($data['team_member_id'])) {
            $meeting->attendees()->attach($data['team_member_id']);
        }

        MeetingPrepItem::create([
            'meeting_id' => $meeting->id,
            'content' => $data['prep_item_content'] ?? $issue->summary,
        ]);

        event(new MeetingScheduled($meeting));

        return $meeting;
    }
}
