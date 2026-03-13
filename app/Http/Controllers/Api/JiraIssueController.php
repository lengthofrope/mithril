<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\JiraIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->successResponse($query->get());
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
            'issues' => $issues,
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
}
