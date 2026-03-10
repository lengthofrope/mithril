@props(['events', 'timezone' => 'Europe/Amsterdam'])

@php
    use App\Enums\CalendarEventStatus;

    $now = now($timezone);

    $statusDotClass = function (CalendarEventStatus $status): string {
        return match ($status) {
            CalendarEventStatus::Free             => 'bg-green-500',
            CalendarEventStatus::Tentative        => 'bg-yellow-500',
            CalendarEventStatus::Busy             => 'bg-red-500',
            CalendarEventStatus::OutOfOffice      => 'bg-gray-400',
            CalendarEventStatus::WorkingElsewhere => 'bg-blue-500',
        };
    };

    $isHappening = function (\App\Models\CalendarEvent $event) use ($now, $timezone): bool {
        return $event->start_at->timezone($timezone)->lte($now)
            && $event->end_at->timezone($timezone)->gte($now);
    };

    $dayLabel = function (\App\Models\CalendarEvent $event) use ($timezone): string {
        $localStart = $event->start_at->timezone($timezone);

        if ($localStart->isToday()) {
            return 'Today';
        }

        if ($localStart->isTomorrow()) {
            return 'Tomorrow';
        }

        return $localStart->format('l, d M');
    };

    $linksJson = function (\App\Models\CalendarEvent $event): string {
        $links = $event->links ?? collect();
        return $links->map(fn ($link) => [
            'id' => $link->id,
            'calendar_event_id' => $link->calendar_event_id,
            'linkable_type' => $link->linkable_type,
            'linkable_id' => $link->linkable_id,
            'created_at' => $link->created_at->toIso8601String(),
        ])->values()->toJson();
    };

    $user = auth()->user();
    $userEmails = collect([$user?->email, $user?->microsoft_email])
        ->filter()
        ->map(fn (string $e) => strtolower($e))
        ->all();

    $emailToMemberId = \App\Models\TeamMember::query()
        ->select('id', 'email', 'microsoft_email')
        ->get()
        ->flatMap(fn ($m) => collect([
            $m->email ? strtolower($m->email) : null,
            $m->microsoft_email ? strtolower($m->microsoft_email) : null,
        ])->filter()->mapWithKeys(fn (string $e) => [$e => $m->id]))
        ->all();

    $canCreateBila = function (\App\Models\CalendarEvent $event) use ($userEmails, $emailToMemberId): bool {
        $attendees = $event->attendees ?? [];
        $candidateEmails = collect($attendees)
            ->map(fn (array $a) => strtolower($a['email'] ?? ''))
            ->filter(fn (string $e) => $e !== '' && !in_array($e, $userEmails, true))
            ->values()
            ->all();

        $matchedMemberIds = collect($candidateEmails)
            ->map(fn (string $e) => $emailToMemberId[$e] ?? null)
            ->filter()
            ->unique()
            ->values();

        return $matchedMemberIds->count() === 1;
    };
@endphp

<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
            Upcoming
        </h2>
        <a
            href="{{ route('calendar.index') }}"
            class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
        >
            View all
        </a>
    </div>

    @if($events->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
            No upcoming events.
        </p>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($events as $event)
                @php
                    $happening = $isHappening($event);
                    $dotClass  = $statusDotClass($event->status);
                    $label     = $dayLabel($event);
                @endphp

                <div
                    x-data="calendarEventActions({{ $event->id }}, {{ $linksJson($event) }}, {{ $canCreateBila($event) ? 'true' : 'false' }})"
                    class="px-5 py-3 {{ $happening ? 'border-l-2 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="h-2 w-2 shrink-0 rounded-full {{ $dotClass }}"
                            aria-label="{{ $event->status->value }}"
                            role="img"
                        ></span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $event->subject }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($event->is_all_day)
                                    {{ $label }} &middot; All day
                                @else
                                    {{ $label }} &middot; {{ $event->start_at->timezone($timezone)->format('H:i') }}&ndash;{{ $event->end_at->timezone($timezone)->format('H:i') }}
                                @endif
                            </p>
                        </div>

                        @if($event->is_online_meeting && $event->online_meeting_url)
                            <a
                                href="{{ $event->online_meeting_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shrink-0 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                title="Join online meeting"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.889L15 14"/><rect x="2" y="7" width="13" height="10" rx="2" ry="2"/>
                                </svg>
                            </a>
                        @endif

                        <x-tl.calendar-event-actions />
                    </div>

                    <x-tl.calendar-event-pills />
                </div>
            @endforeach
        </div>
    @endif
</div>
