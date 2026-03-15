<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\PruneResult;
use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\CalendarEventLink;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\Task;
use App\Models\User;

/**
 * Prunes completed tasks and follow-ups beyond a user's configured retention period.
 *
 * Only tasks and follow-ups are pruned. Bilas, agreements, and notes are never
 * pruned by retention — they are only removed when a team member is deleted
 * (handled by DB cascade constraints).
 */
class DataPruningService
{
    /**
     * Prune completed tasks and follow-ups for a user beyond their retention period.
     *
     * @param User $user The user whose data should be pruned.
     * @return PruneResult Counts of deleted records.
     */
    public function pruneForUser(User $user): PruneResult
    {
        $cutoff = now()->subDays($user->prune_after_days ?? 90);

        $tasksDeleted = Task::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('status', TaskStatus::Done)
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $followUpsDeleted = FollowUp::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('status', FollowUpStatus::Done)
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $emailsDeleted = Email::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->where('updated_at', '<', $cutoff)
            ->delete();

        CalendarEventLink::whereDoesntHave('linkable')
            ->whereHas('calendarEvent', fn ($query) => $query->withoutGlobalScopes()->where('user_id', $user->id))
            ->delete();

        EmailLink::whereDoesntHave('linkable')
            ->where(function ($query) use ($user): void {
                $query->whereNull('email_id')
                    ->orWhereHas('email', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $user->id));
            })
            ->delete();

        $jiraIssuesDeleted = $this->pruneJiraIssues($user, $cutoff);

        JiraIssueLink::whereDoesntHave('linkable')
            ->where(function ($query) use ($user): void {
                $query->whereNull('jira_issue_id')
                    ->orWhereHas('jiraIssue', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $user->id));
            })
            ->delete();

        return new PruneResult($tasksDeleted, $followUpsDeleted, $emailsDeleted, $jiraIssuesDeleted);
    }

    /**
     * Prune Jira issues: dismissed beyond retention + stale (synced_at > 30 days).
     *
     * @param User $user   The user whose Jira issues to prune.
     * @param \Illuminate\Support\Carbon $cutoff The retention cutoff date.
     * @return int Number of Jira issues deleted.
     */
    private function pruneJiraIssues(User $user, \Illuminate\Support\Carbon $cutoff): int
    {
        $dismissed = JiraIssue::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $stale = JiraIssue::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('is_dismissed', false)
            ->where('synced_at', '<', now()->subDays(30))
            ->delete();

        return $dismissed + $stale;
    }

    /**
     * Count what would be pruned without actually deleting (dry-run).
     *
     * @param User $user The user whose data to inspect.
     * @return PruneResult Counts of records that would be deleted.
     */
    public function countForUser(User $user): PruneResult
    {
        $cutoff = now()->subDays($user->prune_after_days ?? 90);

        $tasksCount = Task::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('status', TaskStatus::Done)
            ->where('updated_at', '<', $cutoff)
            ->count();

        $followUpsCount = FollowUp::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('status', FollowUpStatus::Done)
            ->where('updated_at', '<', $cutoff)
            ->count();

        $emailsCount = Email::withoutGlobalScope('user')
            ->where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->where('updated_at', '<', $cutoff)
            ->count();

        return new PruneResult($tasksCount, $followUpsCount, $emailsCount);
    }
}
