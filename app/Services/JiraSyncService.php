<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JiraIssue;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Handles Jira issue synchronization from the Jira Cloud API to the local cache.
 *
 * Fetches issues from three JQL queries (assigned, mentioned, watched), merges
 * sources when an issue appears in multiple queries, and prunes stale issues.
 */
class JiraSyncService
{
    /**
     * JQL queries mapped to their source label.
     *
     * @var array<string, string>
     */
    private const array JQL_QUERIES = [
        'assigned'  => 'assignee = currentUser() ORDER BY updated DESC',
        'mentioned' => 'comment ~ currentUser() ORDER BY updated DESC',
        'watched'   => 'watcher = currentUser() ORDER BY updated DESC',
    ];

    /**
     * Create a new JiraSyncService instance.
     *
     * @param JiraCloudService $jiraCloudService
     */
    public function __construct(
        private readonly JiraCloudService $jiraCloudService,
    ) {}

    /**
     * Sync all Jira issues for the given user.
     *
     * Executes three JQL queries, merges sources, upserts results, and removes
     * stale issues that are no longer in the sync set (preserving dismissed ones).
     *
     * @param User $user The user to sync issues for.
     * @return void
     */
    public function syncIssues(User $user): void
    {
        $maxPerQuery = (int) ceil(config('jira.max_issues_per_sync', 250) / count(self::JQL_QUERIES));
        $issueMap    = [];

        foreach (self::JQL_QUERIES as $source => $jql) {
            $rawIssues = $this->jiraCloudService->searchIssues($user, $jql, $maxPerQuery);

            foreach ($rawIssues as $rawIssue) {
                $jiraId = $rawIssue['id'];

                if (isset($issueMap[$jiraId])) {
                    $issueMap[$jiraId]['sources'][] = $source;
                } else {
                    $issueMap[$jiraId] = $this->normalizeIssue($rawIssue, $source, $user);
                }
            }
        }

        $syncedJiraIds = [];

        foreach ($issueMap as $normalized) {
            $existing = JiraIssue::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('jira_issue_id', $normalized['jira_issue_id'])
                ->first();

            $isDismissed = $existing?->is_dismissed ?? false;

            JiraIssue::withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'user_id'       => $user->id,
                        'jira_issue_id' => $normalized['jira_issue_id'],
                    ],
                    array_merge($normalized, ['is_dismissed' => $isDismissed]),
                );

            $syncedJiraIds[] = $normalized['jira_issue_id'];
        }

        JiraIssue::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_dismissed', false)
            ->whereNotIn('jira_issue_id', $syncedJiraIds)
            ->delete();
    }

    /**
     * Normalize a raw Jira API issue into an array suitable for upsert.
     *
     * @param array<string, mixed> $rawIssue The raw issue from the Jira API.
     * @param string               $source   The source label (assigned, mentioned, watched).
     * @param User                 $user     The user this issue belongs to.
     * @return array<string, mixed> Normalized issue data.
     */
    private function normalizeIssue(array $rawIssue, string $source, User $user): array
    {
        $fields          = $rawIssue['fields'] ?? [];
        $descriptionText = $this->extractDescriptionPreview($fields['description'] ?? null);

        $siteUrl = str_replace(
            ['api.atlassian.com/ex/jira/', '/rest/api/3'],
            ['', ''],
            config('jira.api_base_url') . $user->jira_cloud_id
        );

        return [
            'jira_issue_id'      => $rawIssue['id'],
            'issue_key'          => $rawIssue['key'],
            'summary'            => $fields['summary'] ?? '',
            'description_preview' => $descriptionText ? substr($descriptionText, 0, 500) : null,
            'project_key'        => $fields['project']['key'] ?? '',
            'project_name'       => $fields['project']['name'] ?? '',
            'issue_type'         => $fields['issuetype']['name'] ?? '',
            'status_name'        => $fields['status']['name'] ?? '',
            'status_category'    => $fields['status']['statusCategory']['key'] ?? 'new',
            'priority_name'      => $fields['priority']['name'] ?? null,
            'assignee_name'      => $fields['assignee']['displayName'] ?? null,
            'assignee_email'     => $fields['assignee']['emailAddress'] ?? null,
            'reporter_name'      => $fields['reporter']['displayName'] ?? null,
            'reporter_email'     => $fields['reporter']['emailAddress'] ?? null,
            'labels'             => !empty($fields['labels']) ? $fields['labels'] : null,
            'web_url'            => "https://{$user->jira_cloud_id}.atlassian.net/browse/{$rawIssue['key']}",
            'sources'            => [$source],
            'updated_in_jira_at' => Carbon::parse($fields['updated'] ?? now()),
            'synced_at'          => now(),
        ];
    }

    /**
     * Extract plain text from Jira's Atlassian Document Format description.
     *
     * @param mixed $description The description field (ADF or null).
     * @return string|null Plain text extract, or null if empty.
     */
    private function extractDescriptionPreview(mixed $description): ?string
    {
        if ($description === null || !is_array($description)) {
            return null;
        }

        $texts = [];
        $this->collectTextNodes($description, $texts);

        $result = implode(' ', $texts);

        return $result !== '' ? $result : null;
    }

    /**
     * Recursively collect text nodes from an ADF document tree.
     *
     * @param array<string, mixed> $node  The current ADF node.
     * @param array<int, string>   $texts Collected text strings (by reference).
     * @return void
     */
    private function collectTextNodes(array $node, array &$texts): void
    {
        if (isset($node['text']) && is_string($node['text'])) {
            $texts[] = $node['text'];
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $this->collectTextNodes($child, $texts);
            }
        }
    }
}
