<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceInterval;
use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Handles recurrence logic for recurring tasks.
 *
 * Calculates next deadlines, creates next occurrences,
 * and determines whether a task should recur.
 */
class RecurrenceService
{
    /**
     * Calculate the next deadline based on the current deadline and recurrence interval.
     *
     * Skips past dates to always return a future or today date.
     * If no current deadline is provided, uses today as the base.
     *
     * @param CarbonInterface|null $currentDeadline
     * @param RecurrenceInterval $interval
     * @param int|null $customDays
     * @return Carbon
     */
    public function calculateNextDeadline(
        ?CarbonInterface $currentDeadline,
        RecurrenceInterval $interval,
        ?int $customDays = null,
    ): Carbon {
        $base = $currentDeadline ? $currentDeadline->copy() : Carbon::today();
        $nextDeadline = $this->advanceByInterval($base, $interval, $customDays);

        while ($nextDeadline->isPast() && ! $nextDeadline->isToday()) {
            $nextDeadline = $this->advanceByInterval($nextDeadline, $interval, $customDays);
        }

        return $nextDeadline;
    }

    /**
     * Create the next occurrence of a recurring task.
     *
     * @param Task $completedTask
     * @return Task
     */
    public function createNextOccurrence(Task $completedTask): Task
    {
        $nextDeadline = $this->calculateNextDeadline(
            $completedTask->deadline,
            $completedTask->recurrence_interval,
            $completedTask->recurrence_custom_days,
        );

        return Task::create([
            'title' => $completedTask->title,
            'description' => $completedTask->description,
            'priority' => $completedTask->priority,
            'category' => $completedTask->category,
            'status' => TaskStatus::Open,
            'deadline' => $nextDeadline,
            'team_id' => $completedTask->team_id,
            'team_member_id' => $completedTask->team_member_id,
            'task_group_id' => $completedTask->task_group_id,
            'task_category_id' => $completedTask->task_category_id,
            'is_private' => $completedTask->is_private,
            'is_recurring' => true,
            'recurrence_interval' => $completedTask->recurrence_interval,
            'recurrence_custom_days' => $completedTask->recurrence_custom_days,
            'recurrence_series_id' => $completedTask->recurrence_series_id,
            'recurrence_parent_id' => $completedTask->id,
            'user_id' => $completedTask->user_id,
        ]);
    }

    /**
     * Check whether a task should spawn a next occurrence.
     *
     * Returns true if the task is recurring with a valid interval
     * and was just marked as Done.
     *
     * @param Task $task
     * @param TaskStatus $oldStatus
     * @param TaskStatus $newStatus
     * @return bool
     */
    public function shouldRecur(Task $task, TaskStatus $oldStatus, TaskStatus $newStatus): bool
    {
        if (! $task->is_recurring) {
            return false;
        }

        if ($task->recurrence_interval === null) {
            return false;
        }

        if ($newStatus !== TaskStatus::Done) {
            return false;
        }

        if ($oldStatus === TaskStatus::Done) {
            return false;
        }

        return true;
    }

    /**
     * Stop recurrence for a task.
     *
     * @param Task $task
     * @return void
     */
    public function stopRecurrence(Task $task): void
    {
        $task->update(['is_recurring' => false]);
    }

    /**
     * Advance a date by the given recurrence interval.
     *
     * @param CarbonInterface $date
     * @param RecurrenceInterval $interval
     * @param int|null $customDays
     * @return Carbon
     */
    private function advanceByInterval(
        CarbonInterface $date,
        RecurrenceInterval $interval,
        ?int $customDays = null,
    ): Carbon {
        return match ($interval) {
            RecurrenceInterval::Daily => $date->copy()->addDay(),
            RecurrenceInterval::Weekly => $date->copy()->addWeek(),
            RecurrenceInterval::Biweekly => $date->copy()->addWeeks(2),
            RecurrenceInterval::Monthly => $date->copy()->addMonth(),
            RecurrenceInterval::Custom => $date->copy()->addDays($customDays ?? 1),
        };
    }
}
