<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BilaScheduled;

/**
 * Calculates and persists the next bila date on the team member after a bila is scheduled.
 *
 * Uses the team member's bila_interval_days to project the next meeting date
 * from the scheduled date of the just-created bila.
 */
class ScheduleNextBila
{
    /**
     * Handle the BilaScheduled event.
     *
     * @param BilaScheduled $event
     * @return void
     */
    public function handle(BilaScheduled $event): void
    {
        $bila = $event->bila;
        $teamMember = $bila->teamMember;

        if ($teamMember === null) {
            return;
        }

        $intervalDays = $teamMember->bila_interval_days;

        if ($intervalDays <= 0) {
            return;
        }

        $nextDate = $bila->scheduled_date->copy()->addDays($intervalDays);

        $teamMember->next_bila_date = $nextDate;
        $teamMember->save();
    }
}
