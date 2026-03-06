<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a task's status is changed.
 */
class TaskStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event.
     *
     * @param Task $task The task that was updated.
     * @param TaskStatus $oldStatus The previous status.
     * @param TaskStatus $newStatus The new status.
     */
    public function __construct(
        public readonly Task $task,
        public readonly TaskStatus $oldStatus,
        public readonly TaskStatus $newStatus,
    ) {}
}
