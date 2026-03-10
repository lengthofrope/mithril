<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Queued job that syncs Microsoft Graph availability for all team members
 * of a single user who have status_source set to Microsoft.
 *
 * Members are batched per the configured batch size to avoid hitting Graph
 * request limits. The most restrictive overlapping status is applied when
 * multiple schedule items overlap the current window. Auth failures
 * (revoked consent) are logged as warnings without re-queuing; all other
 * failures trigger the retry backoff.
 */
class SyncMemberAvailabilityJob implements ShouldQueue
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
     * Graph availability view digit → MemberStatus precedence weight.
     *
     * Higher weight = more restrictive. Used to pick the dominant status
     * when multiple slots or schedule items overlap the current window.
     *
     * @var array<string, int>
     */
    private const AVAILABILITY_WEIGHT = [
        '3' => 50, // oof              → Absent
        '2' => 40, // busy             → InAMeeting
        '1' => 30, // tentative        → PartiallyAvailable
        '4' => 20, // workingElsewhere → WorkingElsewhere
        '0' => 10, // free             → Available
    ];

    /**
     * Graph scheduleItems status string → MemberStatus precedence weight.
     *
     * @var array<string, int>
     */
    private const SCHEDULE_ITEM_WEIGHT = [
        'oof'              => 50,
        'busy'             => 40,
        'tentative'        => 30,
        'workingElsewhere' => 20,
        'free'             => 10,
    ];

    /**
     * Create a new job instance.
     *
     * @param User $user The user whose team members' availability should be synced.
     */
    public function __construct(private readonly User $user) {}

    /**
     * Execute the job.
     *
     * Fetches schedule availability from Microsoft Graph for all eligible
     * team members and updates their status and status_synced_at fields.
     *
     * @param MicrosoftGraphService $graph The Graph API service.
     * @return void
     */
    public function handle(MicrosoftGraphService $graph): void
    {
        $members = TeamMember::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->where('status_source', StatusSource::Microsoft->value)
            ->whereNotNull('microsoft_email')
            ->get();

        if ($members->isEmpty()) {
            return;
        }

        $from      = now();
        $to        = now()->addMinutes(60);
        $batchSize = (int) config('microsoft.schedule_batch_size', 20);

        try {
            $availabilityByEmail = $this->fetchAvailabilityByEmail(
                $graph,
                $members,
                $batchSize,
                $from,
                $to
            );

            $this->applyAvailabilityToMembers($members, $availabilityByEmail);
        } catch (RuntimeException $exception) {
            $this->user->refresh();

            if (!$this->user->hasMicrosoftConnection()) {
                Log::warning('Availability sync skipped — Microsoft consent revoked.', [
                    'user_id' => $this->user->id,
                    'reason'  => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }

    /**
     * Fetch availability data from Graph in batches and key the result by email.
     *
     * @param MicrosoftGraphService          $graph
     * @param Collection<int, TeamMember>    $members
     * @param int                            $batchSize
     * @param \Illuminate\Support\Carbon     $from
     * @param \Illuminate\Support\Carbon     $to
     * @return array<string, MemberStatus>
     */
    private function fetchAvailabilityByEmail(
        MicrosoftGraphService $graph,
        Collection $members,
        int $batchSize,
        \Illuminate\Support\Carbon $from,
        \Illuminate\Support\Carbon $to
    ): array {
        $availabilityByEmail = [];

        $emailBatches = $members->pluck('microsoft_email')->unique()->chunk($batchSize);

        foreach ($emailBatches as $emails) {
            $schedules = $graph->getScheduleAvailability($this->user, $emails->toArray(), $from, $to);

            foreach ($schedules as $schedule) {
                $email    = $schedule['email'];
                $resolved = $this->resolveStatusFromAvailability($schedule['availability']);

                if ($resolved !== null) {
                    $availabilityByEmail[$email] = $resolved;
                }
            }
        }

        return $availabilityByEmail;
    }

    /**
     * Apply the resolved availability map to each team member record.
     *
     * @param Collection<int, TeamMember>   $members
     * @param array<string, MemberStatus>   $availabilityByEmail
     * @return void
     */
    private function applyAvailabilityToMembers(Collection $members, array $availabilityByEmail): void
    {
        foreach ($members as $member) {
            $availability = $availabilityByEmail[$member->microsoft_email] ?? null;

            if ($availability === null) {
                continue;
            }

            $member->update([
                'status'           => $availability,
                'status_synced_at' => now(),
            ]);
        }
    }

    /**
     * Resolve a MemberStatus from the Graph availability payload.
     *
     * The payload is either an availabilityView string (e.g. "023") where
     * each character maps to a 60-minute slot, or a scheduleItems array
     * where each element has a 'status' key. Returns null when the payload
     * is empty or contains only unrecognised values.
     *
     * @param string|array<int, array<string, mixed>> $availability
     * @return MemberStatus|null
     */
    private function resolveStatusFromAvailability(string|array $availability): ?MemberStatus
    {
        if (is_string($availability)) {
            return $this->resolveStatusFromAvailabilityView($availability);
        }

        return $this->resolveStatusFromScheduleItems($availability);
    }

    /**
     * Resolve status from an availabilityView string.
     *
     * The first character represents the slot covering the requested window
     * start. When the window spans multiple slots the most restrictive slot
     * value across all characters is used.
     *
     * @param string $availabilityView
     * @return MemberStatus|null
     */
    private function resolveStatusFromAvailabilityView(string $availabilityView): ?MemberStatus
    {
        if ($availabilityView === '') {
            return null;
        }

        $dominantSlot   = '0';
        $dominantWeight = 0;

        foreach (str_split($availabilityView) as $slot) {
            $weight = self::AVAILABILITY_WEIGHT[$slot] ?? 0;

            if ($weight > $dominantWeight) {
                $dominantWeight = $weight;
                $dominantSlot   = $slot;
            }
        }

        return $this->mapAvailabilityViewSlotToStatus($dominantSlot);
    }

    /**
     * Map a single availabilityView slot character to a MemberStatus.
     *
     * @param string $slot
     * @return MemberStatus|null
     */
    private function mapAvailabilityViewSlotToStatus(string $slot): ?MemberStatus
    {
        return match ($slot) {
            '3'     => MemberStatus::Absent,
            '2'     => MemberStatus::InAMeeting,
            '1'     => MemberStatus::PartiallyAvailable,
            '4'     => MemberStatus::WorkingElsewhere,
            '0'     => MemberStatus::Available,
            default => null,
        };
    }

    /**
     * Resolve status from a scheduleItems array by picking the most restrictive entry.
     *
     * @param array<int, array<string, mixed>> $scheduleItems
     * @return MemberStatus|null
     */
    private function resolveStatusFromScheduleItems(array $scheduleItems): ?MemberStatus
    {
        if (empty($scheduleItems)) {
            return null;
        }

        $dominantStatus = null;
        $dominantWeight = 0;

        foreach ($scheduleItems as $item) {
            $status = (string) ($item['status'] ?? '');
            $weight = self::SCHEDULE_ITEM_WEIGHT[$status] ?? 0;

            if ($weight > $dominantWeight) {
                $dominantWeight = $weight;
                $dominantStatus = $status;
            }
        }

        if ($dominantStatus === null) {
            return null;
        }

        return $this->mapScheduleItemStatusToMemberStatus($dominantStatus);
    }

    /**
     * Map a Graph scheduleItems status string to a MemberStatus.
     *
     * @param string $status
     * @return MemberStatus|null
     */
    private function mapScheduleItemStatusToMemberStatus(string $status): ?MemberStatus
    {
        return match ($status) {
            'oof'              => MemberStatus::Absent,
            'busy'             => MemberStatus::InAMeeting,
            'tentative'        => MemberStatus::PartiallyAvailable,
            'workingElsewhere' => MemberStatus::WorkingElsewhere,
            'free'             => MemberStatus::Available,
            default            => null,
        };
    }
}
