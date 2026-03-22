<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingScheduled;

/**
 * Calculates and persists the next meeting date on attendee team members.
 *
 * Uses the team member's meeting_interval_days to project the next meeting date
 * from the scheduled date of the just-created meeting. Only applies to one_on_one
 * meetings with a single attendee.
 */
class ScheduleNextMeeting
{
    /**
     * Handle the MeetingScheduled event.
     *
     * @param MeetingScheduled $event
     * @return void
     */
    public function handle(MeetingScheduled $event): void
    {
        $meeting = $event->meeting;

        if ($meeting->type->value !== 'one_on_one') {
            return;
        }

        $attendees = $meeting->attendees;

        if ($attendees->count() !== 1) {
            return;
        }

        $teamMember = $attendees->first();

        if ($teamMember === null) {
            return;
        }

        $intervalDays = $teamMember->meeting_interval_days;

        if ($intervalDays <= 0) {
            return;
        }

        $nextDate = $meeting->scheduled_at->copy()->addDays($intervalDays);

        $teamMember->next_meeting_date = $nextDate;
        $teamMember->save();
    }
}
