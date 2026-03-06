<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Models\FollowUp;

/**
 * Creates a follow-up automatically when a task transitions to the 'waiting' status.
 *
 * Only creates a follow-up if none already exists for the task.
 */
class CreateFollowUpOnWaiting
{
    /**
     * Handle the TaskStatusChanged event.
     *
     * @param TaskStatusChanged $event
     * @return void
     */
    public function handle(TaskStatusChanged $event): void
    {
        if ($event->newStatus !== TaskStatus::Waiting) {
            return;
        }

        $alreadyExists = FollowUp::where('task_id', $event->task->id)
            ->whereNot('status', FollowUpStatus::Done->value)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        FollowUp::create([
            'task_id' => $event->task->id,
            'team_member_id' => $event->task->team_member_id,
            'description' => $event->task->title,
            'waiting_on' => null,
            'follow_up_date' => now()->addDays(3)->toDateString(),
            'status' => FollowUpStatus::Open->value,
        ]);
    }
}
