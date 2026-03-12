<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\BilaScheduled;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\FollowUp;
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
            'bila' => $this->createBila($jiraIssue, $data),
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
     * Create a Bila resource from a Jira issue.
     *
     * If an upcoming Bila exists for the team member, adds a prep item instead.
     *
     * @param JiraIssue            $issue The source Jira issue.
     * @param array<string, mixed> $data  The merged prefill + request data.
     * @return Bila The created or existing Bila.
     */
    private function createBila(JiraIssue $issue, array $data): Bila
    {
        $existingBila = Bila::query()
            ->where('team_member_id', $data['team_member_id'])
            ->where('is_done', false)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if ($existingBila) {
            BilaPrepItem::create([
                'bila_id' => $existingBila->id,
                'content' => $data['prep_item_content'] ?? $issue->summary,
            ]);

            return $existingBila;
        }

        $bila = Bila::create([
            'team_member_id' => $data['team_member_id'],
            'scheduled_date' => now()->addDays(7)->toDateString(),
        ]);

        BilaPrepItem::create([
            'bila_id' => $bila->id,
            'content' => $data['prep_item_content'] ?? $issue->summary,
        ]);

        if (class_exists(BilaScheduled::class)) {
            event(new BilaScheduled($bila));
        }

        return $bila;
    }
}
