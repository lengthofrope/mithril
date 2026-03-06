<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Bila;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a bila (1-on-1 meeting) is scheduled.
 */
class BilaScheduled
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event.
     *
     * @param Bila $bila The bila that was scheduled.
     */
    public function __construct(
        public readonly Bila $bila,
    ) {}
}
