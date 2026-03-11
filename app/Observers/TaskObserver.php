<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Models\Task;

/**
 * Observes Task model lifecycle events to dispatch domain events.
 */
class TaskObserver
{
    /**
     * Handle the Task "updated" event.
     *
     * Dispatches TaskStatusChanged when the status field has been modified.
     *
     * @param Task $task
     * @return void
     */
    public function updated(Task $task): void
    {
        if (! $task->wasChanged('status')) {
            return;
        }

        $originalValue = $task->getOriginal('status');
        $oldStatus = $originalValue instanceof TaskStatus
            ? $originalValue
            : TaskStatus::from($originalValue);
        $newStatus = $task->status;

        TaskStatusChanged::dispatch($task, $oldStatus, $newStatus);
    }
}
