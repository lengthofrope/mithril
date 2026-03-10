<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Queued job that syncs Microsoft Graph calendar events for a single user.
 *
 * Fetches events within the configured look-ahead window, upserts them into
 * the calendar_events table, and deletes any local records that no longer
 * exist in the Graph response. Auth failures (revoked consent) are logged as
 * warnings without re-queuing; all other failures trigger the retry backoff.
 */
class SyncCalendarEventsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of attempts before the job is considered failed.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Backoff delays in seconds between each retry attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     *
     * @param User $user The user whose calendar events should be synced.
     */
    public function __construct(private readonly User $user) {}

    /**
     * Execute the job.
     *
     * Fetches events from Microsoft Graph for the configured look-ahead window,
     * upserts them locally, and removes stale records outside the Graph response.
     *
     * @param MicrosoftGraphService $graph The Graph API service to use for fetching events.
     * @return void
     */
    public function handle(MicrosoftGraphService $graph): void
    {
        $from = now()->startOfDay();
        $to   = now()->addDays(config('microsoft.calendar_days_ahead', 7))->endOfDay();

        try {
            $events = $graph->getMyCalendarEvents($this->user, $from, $to);

            $syncedEventIds = [];

            foreach ($events as $eventData) {
                $calendarEvent = CalendarEvent::withoutGlobalScopes()
                    ->updateOrCreate(
                        [
                            'user_id'            => $this->user->id,
                            'microsoft_event_id' => $eventData['microsoft_event_id'],
                        ],
                        [
                            'subject'            => $eventData['subject'],
                            'start_at'           => $eventData['start_at'],
                            'end_at'             => $eventData['end_at'],
                            'is_all_day'         => $eventData['is_all_day'],
                            'location'           => $eventData['location'],
                            'status'             => $eventData['status'],
                            'is_online_meeting'  => $eventData['is_online_meeting'],
                            'online_meeting_url' => $eventData['online_meeting_url'],
                            'organizer_name'     => $eventData['organizer_name'],
                            'organizer_email'    => $eventData['organizer_email'],
                            'attendees'          => $eventData['attendees'] ?? [],
                            'synced_at'          => now(),
                        ]
                    );

                $syncedEventIds[] = $calendarEvent->id;
            }

            CalendarEvent::withoutGlobalScopes()
                ->where('user_id', $this->user->id)
                ->where('start_at', '>=', $from)
                ->where('start_at', '<=', $to)
                ->whereNotIn('id', $syncedEventIds)
                ->delete();
        } catch (RuntimeException $exception) {
            $this->user->refresh();

            if (!$this->user->hasMicrosoftConnection()) {
                Log::warning('Calendar sync skipped — Microsoft consent revoked.', [
                    'user_id' => $this->user->id,
                    'reason'  => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }
}
