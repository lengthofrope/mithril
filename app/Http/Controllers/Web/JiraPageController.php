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

        $source = $request->input('source', 'assigned');

        $query = JiraIssue::query()
            ->with('jiraIssueLinks')
            ->whereJsonContains('sources', $source)
            ->orderByDesc('updated_in_jira_at');

        if (!$request->boolean('show_dismissed')) {
            $query->where('is_dismissed', false);
        }

        if ($request->filled('status_category')) {
            $query->where('status_category', $request->input('status_category'));
        } else {
            $query->where('status_category', '!=', 'done');
        }

        $allFilteredIssues = $query->get();

        $projectOptions = $allFilteredIssues
            ->unique('project_key')
            ->sortBy('project_name')
            ->map(fn (JiraIssue $issue): array => [
                'value' => $issue->project_key,
                'label' => $issue->project_name,
            ])
            ->values();

        $selectedProjectKey = $request->input('project_key');

        if ($selectedProjectKey && !$projectOptions->contains('value', $selectedProjectKey)) {
            $selectedProject = JiraIssue::query()
                ->where('project_key', $selectedProjectKey)
                ->first(['project_key', 'project_name']);

            if ($selectedProject) {
                $projectOptions->push([
                    'value' => $selectedProject->project_key,
                    'label' => $selectedProject->project_name,
                ]);
                $projectOptions = $projectOptions->sortBy('label')->values();
            }
        }

        $projectOptions = $projectOptions->all();

        $issues = $selectedProjectKey
            ? $allFilteredIssues->where('project_key', $selectedProjectKey)->values()
            : $allFilteredIssues;

        $groupedIssues = $issues->groupBy('project_key');

        return view('pages.jira', [
            'title'          => 'Jira',
            'isJiraConnected' => true,
            'issues'         => $issues,
            'groupedIssues'  => $groupedIssues,
            'projectOptions' => $projectOptions,
        ]);
    }
}
