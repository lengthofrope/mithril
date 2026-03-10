<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Handles the business logic for creating and linking resources to calendar events.
 *
 * Responsibilities:
 * - Resolving which team member to auto-assign from event attendees
 * - Building pre-fill data arrays for each supported resource type
 * - Creating and deduplicating CalendarEventLink records
 * - Retrieving all linked resources for a given event
 */
class CalendarActionService
{
    /**
     * Resolve which team member should be auto-assigned from the event's attendees.
     *
     * Compares attendee emails against TeamMember.microsoft_email and TeamMember.email
     * (case-insensitive), excluding the authenticated user's own emails.
     * Returns null if zero or two or more members match.
     *
     * @param CalendarEvent $event The calendar event to resolve attendees for.
     * @return TeamMember|null The matched member, or null if ambiguous or empty.
     */
    public function resolveTeamMember(CalendarEvent $event): ?TeamMember
    {
        $attendees = $event->attendees;

        if (empty($attendees)) {
            return null;
        }

        $userEmails = $this->resolveUserEmails();

        $candidateEmails = collect($attendees)
            ->map(fn (array $attendee): string => strtolower($attendee['email'] ?? ''))
            ->filter(fn (string $email): bool => $email !== '' && !in_array($email, $userEmails, true))
            ->values()
            ->all();

        if (empty($candidateEmails)) {
            return null;
        }

        $members = TeamMember::query()
            ->where(function ($query) use ($candidateEmails): void {
                foreach ($candidateEmails as $email) {
                    $query->orWhereRaw('LOWER(microsoft_email) = ?', [$email])
                          ->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->get();

        $uniqueMembers = $members->unique('id');

        if ($uniqueMembers->count() !== 1) {
            return null;
        }

        return $uniqueMembers->first();
    }

    /**
     * Build pre-fill data array for a given resource type.
     *
     * Resolves the matching team member and maps event fields to the resource's
     * expected input fields. Throws if an unsupported resource type is given.
     *
     * @param CalendarEvent $event        The source calendar event.
     * @param string        $resourceType One of: bila, task, follow-up, note.
     * @return array<string, mixed> Pre-fill data keyed by field name.
     * @throws \InvalidArgumentException When resourceType is not supported.
     */
    public function buildPrefillData(CalendarEvent $event, string $resourceType): array
    {
        $teamMember = $this->resolveTeamMember($event);

        $base = [
            'team_member_id'   => $teamMember?->id,
            'team_member_name' => $teamMember?->name,
        ];

        return match ($resourceType) {
            'bila' => array_merge($base, [
                'scheduled_date' => $event->start_at->toDateString(),
            ]),
            'task' => array_merge($base, [
                'title'    => $event->subject,
                'deadline' => $event->start_at->toDateString(),
            ]),
            'follow-up' => array_merge($base, [
                'description'    => $event->subject,
                'follow_up_date' => $event->start_at->toDateString(),
            ]),
            'note' => array_merge($base, [
                'title' => $event->subject,
            ]),
            default => throw new \InvalidArgumentException("Invalid resource type: {$resourceType}"),
        };
    }

    /**
     * Create a link between a calendar event and a resource.
     *
     * Uses firstOrCreate to prevent duplicate links for the same event/resource pair.
     *
     * @param CalendarEvent $event    The calendar event to link from.
     * @param Model         $resource The resource to link to (Bila, Task, FollowUp, or Note).
     * @return CalendarEventLink The existing or newly created link.
     */
    public function linkResource(CalendarEvent $event, Model $resource): CalendarEventLink
    {
        return CalendarEventLink::firstOrCreate([
            'calendar_event_id' => $event->id,
            'linkable_type'     => $resource::class,
            'linkable_id'       => $resource->getKey(),
        ]);
    }

    /**
     * Get all linked resources for a calendar event, with linkable models eager-loaded.
     *
     * @param CalendarEvent $event The calendar event to retrieve links for.
     * @return Collection<int, CalendarEventLink>
     */
    public function getLinkedResources(CalendarEvent $event): Collection
    {
        return $event->links()->with('linkable')->get();
    }

    /**
     * Resolve the authenticated user's email addresses in lowercase.
     *
     * Collects both the regular email and microsoft_email for exclusion during
     * attendee matching, filtering out any null values.
     *
     * @return list<string> Lowercased email addresses belonging to the current user.
     */
    private function resolveUserEmails(): array
    {
        $user = auth()->user();

        return collect([
            $user?->email,
            $user?->microsoft_email,
        ])
            ->filter()
            ->map(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }
}
