<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JiraIssue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the dedicated Jira issues browse page.
 *
 * Displays all synced Jira issues with filtering by source, status category,
 * and project. Issues are grouped by project for visual organization.
 */
class JiraPageController extends Controller
{
    /**
     * Display the Jira issues browse page.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $isJiraConnected = $request->user()->hasJiraConnection();

        if (!$isJiraConnected) {
            return view('pages.jira', [
                'title'          => 'Jira',
                'isJiraConnected' => false,
                'issues'         => collect(),
                'groupedIssues'  => collect(),
                'projectOptions' => [],
            ]);
        }

        $query = JiraIssue::query()
            ->with('jiraIssueLinks')
            ->orderByDesc('updated_in_jira_at');

        if (!$request->boolean('show_dismissed')) {
            $query->where('is_dismissed', false);
        }

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
        $groupedIssues = $issues->groupBy('project_key');

        $projectOptions = JiraIssue::query()
            ->where('is_dismissed', false)
            ->select('project_key', 'project_name')
            ->distinct()
            ->orderBy('project_name')
            ->get()
            ->map(fn (JiraIssue $issue): array => [
                'value' => $issue->project_key,
                'label' => $issue->project_name,
            ])
            ->all();

        return view('pages.jira', [
            'title'          => 'Jira',
            'isJiraConnected' => true,
            'issues'         => $issues,
            'groupedIssues'  => $groupedIssues,
            'projectOptions' => $projectOptions,
        ]);
    }
}
