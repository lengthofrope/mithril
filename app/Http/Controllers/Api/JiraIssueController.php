<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\JiraIssue;
use App\Services\JiraUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * API controller for Jira issue operations.
 *
 * Provides JSON endpoints for listing, filtering, dismissing, and
 * fetching dashboard data for synced Jira issues.
 */
class JiraIssueController extends Controller
{
    use ApiResponse;

    /**
     * @param JiraUserService $jiraUserService
     */
    public function __construct(
        private readonly JiraUserService $jiraUserService,
    ) {}

    /**
     * List all non-dismissed Jira issues with optional filters.
     *
     * GET /api/v1/jira-issues
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = JiraIssue::query()
            ->with('jiraIssueLinks')
            ->where('is_dismissed', false)
            ->orderByDesc('updated_in_jira_at');

        if ($request->filled('source')) {
            $query->whereJsonContains('sources', $request->input('source'));
        }

        if ($request->filled('status_category')) {
            $query->where('status_category', $request->input('status_category'));
        }

        if ($request->filled('project_key')) {
            $query->where('project_key', $request->input('project_key'));
        }

        $issues = $query->get();

        return $this->successResponse(
            $this->appendUserNames($request, $issues),
        );
    }

    /**
     * Return assigned open Jira issues for the dashboard widget.
     *
     * GET /api/v1/jira-issues/dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 5);

        $query = JiraIssue::query()
            ->where('is_dismissed', false)
            ->whereJsonContains('sources', 'assigned')
            ->where('status_category', '!=', 'done')
            ->orderByRaw("CASE priority_name WHEN 'Highest' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 WHEN 'Lowest' THEN 5 ELSE 6 END")
            ->orderByDesc('updated_in_jira_at');

        $total = $query->count();
        $issues = $query->limit($limit)->get();

        return $this->successResponse([
            'issues' => $this->appendUserNames($request, $issues),
            'total'  => $total,
        ]);
    }

    /**
     * Mark a Jira issue as dismissed.
     *
     * PATCH /api/v1/jira-issues/{jiraIssue}/dismiss
     *
     * @param JiraIssue $jiraIssue
     * @return JsonResponse
     */
    public function dismiss(JiraIssue $jiraIssue): JsonResponse
    {
        $jiraIssue->update(['is_dismissed' => true]);

        return $this->successResponse($jiraIssue->fresh(), 'Issue dismissed.');
    }

    /**
     * Restore a dismissed Jira issue.
     *
     * PATCH /api/v1/jira-issues/{jiraIssue}/undismiss
     *
     * @param JiraIssue $jiraIssue
     * @return JsonResponse
     */
    public function undismiss(JiraIssue $jiraIssue): JsonResponse
    {
        $jiraIssue->update(['is_dismissed' => false]);

        return $this->successResponse($jiraIssue->fresh(), 'Issue restored.');
    }

    /**
     * Resolve display names for all account IDs in a collection of issues.
     *
     * @param Request                     $request
     * @param Collection<int, JiraIssue>  $issues
     * @return array<string, string>
     */
    private function resolveUserNames(Request $request, Collection $issues): array
    {
        $user = $request->user();

        if (!$user->hasJiraConnection()) {
            return [];
        }

        $accountIds = $issues
            ->flatMap(fn (JiraIssue $issue): array => [
                $issue->assignee_account_id,
                $issue->reporter_account_id,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->jiraUserService->resolveDisplayNames($user, $accountIds);
    }

    /**
     * Append resolved user names to each issue in the collection.
     *
     * @param Request                     $request
     * @param Collection<int, JiraIssue>  $issues
     * @return Collection<int, array<string, mixed>>
     */
    private function appendUserNames(Request $request, Collection $issues): Collection
    {
        $userNames = $this->resolveUserNames($request, $issues);

        return $issues->map(fn (JiraIssue $issue) => array_merge(
            $issue->toArray(),
            [
                'assignee_name' => $userNames[$issue->assignee_account_id] ?? null,
                'reporter_name' => $userNames[$issue->reporter_account_id] ?? null,
            ],
        ));
    }
}
