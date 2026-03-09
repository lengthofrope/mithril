<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FollowUpStatus;
use App\Models\Agreement;
use App\Models\FollowUp;

/**
 * Observer for Agreement model that creates follow-ups when a follow_up_date is set.
 *
 * Uses a fire-and-forget approach: a follow-up is created once and then lives independently.
 */
class AgreementObserver
{
    /**
     * Handle the Agreement "created" event.
     *
     * @param Agreement $agreement
     * @return void
     */
    public function created(Agreement $agreement): void
    {
        $this->createFollowUpIfNeeded($agreement);
    }

    /**
     * Handle the Agreement "updated" event.
     *
     * @param Agreement $agreement
     * @return void
     */
    public function updated(Agreement $agreement): void
    {
        if (!$agreement->wasChanged('follow_up_date')) {
            return;
        }

        $this->createFollowUpIfNeeded($agreement);
    }

    /**
     * Create a follow-up for the agreement when a follow_up_date is present.
     *
     * @param Agreement $agreement
     * @return void
     */
    private function createFollowUpIfNeeded(Agreement $agreement): void
    {
        if ($agreement->follow_up_date === null) {
            return;
        }

        FollowUp::create([
            'user_id' => $agreement->user_id,
            'task_id' => null,
            'team_member_id' => $agreement->team_member_id,
            'description' => "Agreement: {$agreement->description}",
            'waiting_on' => null,
            'follow_up_date' => $agreement->follow_up_date,
            'status' => FollowUpStatus::Open->value,
        ]);
    }
}
