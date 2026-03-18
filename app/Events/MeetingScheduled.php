<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a meeting is scheduled.
 */
class MeetingScheduled
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event.
     *
     * @param Meeting $meeting The meeting that was scheduled.
     */
    public function __construct(
        public readonly Meeting $meeting,
    ) {}
}
