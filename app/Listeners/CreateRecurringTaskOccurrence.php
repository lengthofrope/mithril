<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskStatusChanged;
use App\Services\RecurrenceService;

/**
 * Creates the next occurrence of a recurring task when it is marked as Done.
 */
class CreateRecurringTaskOccurrence
{
    /**
     * Create the listener.
     *
     * @param RecurrenceService $recurrenceService
     */
    public function __construct(
        private readonly RecurrenceService $recurrenceService,
    ) {}

    /**
     * Handle the TaskStatusChanged event.
     *
     * @param TaskStatusChanged $event
     * @return void
     */
    public function handle(TaskStatusChanged $event): void
    {
        if (! $this->recurrenceService->shouldRecur($event->task, $event->oldStatus, $event->newStatus)) {
            return;
        }

        $this->recurrenceService->createNextOccurrence($event->task);
    }
}
