<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FollowUp;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a follow-up's due date has been reached.
 */
class FollowUpDue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event.
     *
     * @param FollowUp $followUp The follow-up that is now due.
     */
    public function __construct(
        public readonly FollowUp $followUp,
    ) {}
}
