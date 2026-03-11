<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\PruneResult;
use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
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
        $cutoff = now()->subDays($user->prune_after_days);

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

        CalendarEventLink::whereDoesntHave('linkable')->delete();

        return new PruneResult($tasksDeleted, $followUpsDeleted);
    }

    /**
     * Count what would be pruned without actually deleting (dry-run).
     *
     * @param User $user The user whose data to inspect.
     * @return PruneResult Counts of records that would be deleted.
     */
    public function countForUser(User $user): PruneResult
    {
        $cutoff = now()->subDays($user->prune_after_days);

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

        return new PruneResult($tasksCount, $followUpsCount);
    }
}
