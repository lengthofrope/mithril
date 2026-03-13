<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles the business logic for creating and linking resources to Jira issues.
 *
 * Resolves team members by Jira account ID (with email fallback),
 * builds pre-fill data for resource creation, and manages polymorphic
 * links between Jira issues and application resources.
 */
class JiraActionService
{
    /**
     * Map Jira priority names to application task priority values.
     *
     * @var array<string, string>
     */
    private const array PRIORITY_MAP = [
        'Highest' => 'urgent',
        'High'    => 'high',
        'Medium'  => 'normal',
        'Low'     => 'low',
        'Lowest'  => 'low',
    ];

    /**
     * Create a new JiraActionService instance.
     *
     * @param JiraCloudService $jiraCloudService
     */
    public function __construct(
        private readonly JiraCloudService $jiraCloudService,
    ) {}

    /**
     * Resolve which team member matches the issue's assignee.
     *
     * First matches by jira_account_id. If no match, fetches the email from
     * the Jira API as a best-effort fallback and matches against email columns.
     * When the fallback succeeds, auto-populates jira_account_id for future matches.
     *
     * @param JiraIssue $issue The Jira issue to resolve the assignee for.
     * @return TeamMember|null The matched team member, or null.
     */
    public function resolveTeamMember(JiraIssue $issue): ?TeamMember
    {
        if ($issue->assignee_account_id === null) {
            return null;
        }

        $member = TeamMember::query()
            ->where('jira_account_id', $issue->assignee_account_id)
            ->first();

        if ($member !== null) {
            return $member;
        }

        return $this->resolveByEmailFallback($issue);
    }

    /**
     * Build pre-fill data for creating a resource from a Jira issue.
     *
     * @param JiraIssue $issue        The source Jira issue.
     * @param string    $resourceType One of: task, follow-up, note, bila.
     * @return array<string, mixed> Pre-fill data keyed by field name.
     * @throws \InvalidArgumentException When resourceType is not supported or bila has no team member.
     */
    public function buildPrefillData(JiraIssue $issue, string $resourceType): array
    {
        $teamMember = $this->resolveTeamMember($issue);

        $base = [
            'team_member_id'   => $teamMember?->id,
            'team_member_name' => $teamMember?->name,
        ];

        return match ($resourceType) {
            'task' => array_merge($base, [
                'title'    => $issue->summary,
                'priority' => self::PRIORITY_MAP[$issue->priority_name] ?? 'normal',
            ]),
            'follow-up' => array_merge($base, [
                'description'    => $issue->summary,
                'follow_up_date' => now()->addDays(3)->toDateString(),
            ]),
            'note' => array_merge($base, [
                'title'   => "{$issue->issue_key} {$issue->summary}",
                'content' => $issue->description_preview,
            ]),
            'bila' => $this->buildBilaPrefill($issue, $teamMember, $base),
            default => throw new \InvalidArgumentException("Invalid resource type: {$resourceType}"),
        };
    }

    /**
     * Create a link between a Jira issue and a resource.
     *
     * Uses firstOrCreate to prevent duplicate links.
     *
     * @param JiraIssue $issue    The Jira issue to link from.
     * @param Model     $resource The resource to link to.
     * @return JiraIssueLink The existing or newly created link.
     */
    public function linkResource(JiraIssue $issue, Model $resource): JiraIssueLink
    {
        return JiraIssueLink::firstOrCreate(
            [
                'jira_issue_id' => $issue->id,
                'linkable_type' => $resource::class,
                'linkable_id'   => $resource->getKey(),
            ],
            [
                'issue_key'     => $issue->issue_key,
                'issue_summary' => $issue->summary,
            ],
        );
    }

    /**
     * Attempt to resolve a team member by fetching their email from the Jira API.
     *
     * If matched, auto-populates the jira_account_id on the team member for future lookups.
     *
     * @param JiraIssue $issue The Jira issue with assignee_account_id set.
     * @return TeamMember|null The matched team member, or null.
     */
    private function resolveByEmailFallback(JiraIssue $issue): ?TeamMember
    {
        try {
            $user  = $issue->user ?? auth()->user();
            $users = $this->jiraCloudService->fetchUsersBulk($user, [$issue->assignee_account_id]);

            $email = $users->first()['emailAddress'] ?? null;

            if ($email === null) {
                return null;
            }

            $email  = strtolower($email);
            $member = TeamMember::query()
                ->where(function ($query) use ($email): void {
                    $query->whereRaw('LOWER(email) = ?', [$email])
                          ->orWhereRaw('LOWER(microsoft_email) = ?', [$email]);
                })
                ->first();

            if ($member !== null) {
                $member->update(['jira_account_id' => $issue->assignee_account_id]);
            }

            return $member;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build pre-fill data for a bila resource.
     *
     * @param JiraIssue       $issue      The source Jira issue.
     * @param TeamMember|null $teamMember The resolved team member.
     * @param array<string, mixed> $base  Base pre-fill data.
     * @return array<string, mixed> Pre-fill data for bila creation.
     * @throws \InvalidArgumentException When the assignee is not a team member.
     */
    private function buildBilaPrefill(JiraIssue $issue, ?TeamMember $teamMember, array $base): array
    {
        if ($teamMember === null) {
            throw new \InvalidArgumentException(
                'Cannot create bila: assignee is not a team member.'
            );
        }

        return array_merge($base, [
            'prep_item_content' => $issue->summary,
        ]);
    }
}
