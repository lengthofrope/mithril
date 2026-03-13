@props(['events', 'isMicrosoftConnected' => false, 'timezone' => 'Europe/Amsterdam'])

@php
    use App\Enums\CalendarEventStatus;

    $now = now($timezone);

    /**
     * Return the Tailwind dot color class for a given CalendarEventStatus enum value.
     */
    $statusDotClass = function (CalendarEventStatus $status): string {
        return match ($status) {
            CalendarEventStatus::Free             => 'bg-green-500',
            CalendarEventStatus::Tentative        => 'bg-yellow-500',
            CalendarEventStatus::Busy             => 'bg-red-500',
            CalendarEventStatus::OutOfOffice      => 'bg-gray-400',
            CalendarEventStatus::WorkingElsewhere => 'bg-blue-500',
        };
    };

    /**
     * Return whether an event is currently in progress.
     */
    $isHappening = function (\App\Models\CalendarEvent $event) use ($now, $timezone): bool {
        return $event->start_at->timezone($timezone)->lte($now)
            && $event->end_at->timezone($timezone)->gte($now);
    };

    /**
     * Return whether an event has already ended.
     */
    $isPast = function (\App\Models\CalendarEvent $event) use ($now, $timezone): bool {
        return $event->end_at->timezone($timezone)->lt($now);
    };

    /**
     * Group an event collection by a human-readable day label.
     *
     * Generates all 7 days in the window so that empty days (weekends, quiet days)
     * are still visible in the calendar view.
     *
     * @param \Illuminate\Database\Eloquent\Collection $collection
     * @return array<string, \Illuminate\Database\Eloquent\Collection>
     */
    $groupByDay = function (\Illuminate\Database\Eloquent\Collection $collection) use ($now, $timezone): array {
        $groups = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $now->copy()->addDays($i)->startOfDay();

            if ($day->isToday()) {
                $label = 'Today';
            } elseif ($day->isTomorrow()) {
                $label = 'Tomorrow';
            } else {
                $label = $day->format('l, d F');
            }

            $groups[$label] = collect();
        }

        foreach ($collection as $event) {
            $localStart = $event->start_at->timezone($timezone);

            if ($localStart->isToday()) {
                $label = 'Today';
            } elseif ($localStart->isTomorrow()) {
                $label = 'Tomorrow';
            } else {
                $label = $localStart->format('l, d F');
            }

            $groups[$label] ??= collect();
            $groups[$label]->push($event);
        }

        return $groups;
    };

    /**
     * Serialize an event's links for the Alpine calendarEventActions component.
     */
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

    /**
     * Pre-compute which events can create a Bila (have exactly one matching team member).
     * Uses a single query for all team member emails to avoid N+1.
     */
    $user = auth()->user();
    $userEmails = collect([$user?->email, $user?->microsoft_email])
        ->filter()
        ->map(fn (string $e) => strtolower($e))
        ->all();

    /**
     * Map of lowercase email → team member ID for matching attendees to members.
     * Each member can have up to two entries (email + microsoft_email).
     */
    $emailToMemberId = \App\Models\TeamMember::query()
        ->select('id', 'email', 'microsoft_email')
        ->get()
        ->flatMap(fn ($m) => collect([
            $m->email ? strtolower($m->email) : null,
            $m->microsoft_email ? strtolower($m->microsoft_email) : null,
        ])->filter()->mapWithKeys(fn (string $e) => [$e => $m->id]))
        ->all();

    /**
     * Return attendee names for display, excluding the current user.
     *
     * @return list<string>
     */
    $attendeeNames = function (\App\Models\CalendarEvent $event) use ($userEmails): array {
        return collect($event->attendees ?? [])
            ->filter(fn (array $a) => !in_array(strtolower($a['email'] ?? ''), $userEmails, true))
            ->map(fn (array $a) => $a['name'] ?? $a['email'] ?? '')
            ->filter()
            ->values()
            ->all();
    };

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

    $grouped = $groupByDay($events);

    /**
     * Separate "Today" and "Tomorrow" groups (displayed side by side)
     * from remaining groups (collapsible, closed by default).
     */
    $prominentLabels = ['Today', 'Tomorrow'];
    $prominentGroups = array_intersect_key($grouped, array_flip($prominentLabels));
    $laterGroups     = array_diff_key($grouped, $prominentGroups);
@endphp

<section
    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    aria-label="Calendar events this week"
>
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
            Calendar
        </h2>

        @if($events->isNotEmpty())
            <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-600 dark:bg-teal-500/15 dark:text-teal-400">
                {{ $events->count() }}
            </span>
        @endif
    </div>

    {{-- Body --}}
    @if(!$isMicrosoftConnected && $events->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
            Connect your Office 365 account to see your calendar.
            <a
                href="{{ route('settings.index') }}"
                class="mt-1 block font-medium text-blue-600 hover:underline dark:text-blue-400"
            >
                Go to Settings
            </a>
        </p>
    @else
        {{-- Today & Tomorrow: side by side on desktop --}}
        @if(!empty($prominentGroups))
            <div class="grid grid-cols-1 xl:grid-cols-2 divide-y xl:divide-y-0 xl:divide-x divide-gray-100 dark:divide-gray-800">
                @foreach($prominentGroups as $dayLabel => $dayEvents)
                    <div x-data="{ open: true }">
                        {{-- Day group header --}}
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-label="{{ $dayLabel }}"
                        >
                            <span>{{ $dayLabel }}</span>
                            <svg
                                class="h-3.5 w-3.5 shrink-0 transition-transform"
                                :class="{ 'rotate-180': open }"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        {{-- Events --}}
                        <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-gray-800">
                            @if($dayEvents->isEmpty())
                                <p class="px-5 py-3 text-sm text-gray-400 dark:text-gray-500">
                                    No events
                                </p>
                            @endif
                            @php $hasPastEvent = false; $nowLineInserted = false; @endphp
                            @foreach($dayEvents as $event)
                                @php
                                    $happening = $isHappening($event);
                                    $past      = $isPast($event);
                                    $dotClass  = $statusDotClass($event->status);
                                @endphp

                                {{-- "Now" divider — only between past and upcoming events (not before first or after last) --}}
                                @if($dayLabel === 'Today' && !$nowLineInserted && !$past && $hasPastEvent)
                                    @php $nowLineInserted = true; @endphp
                                    <div class="elvish-divider mx-5" aria-hidden="true">
                                        <span class="elvish-divider-leaf"></span>
                                    </div>
                                @endif

                                @if($past) @php $hasPastEvent = true; @endphp @endif

                                <div
                                    x-data="calendarEventActions({{ $event->id }}, {{ $linksJson($event) }}, {{ $canCreateBila($event) ? 'true' : 'false' }})"
                                    class="flex items-start gap-3 px-5 py-3 {{ $happening ? 'border-l-2 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                                    role="row"
                                >
                                    {{-- Time column --}}
                                    <div
                                        class="w-16 shrink-0 text-xs text-gray-500 dark:text-gray-400 {{ $past ? 'opacity-40' : '' }}"
                                        aria-label="{{ $event->is_all_day ? 'All day' : $event->start_at->timezone($timezone)->format('H:i') . ' to ' . $event->end_at->timezone($timezone)->format('H:i') }}"
                                    >
                                        @if($event->is_all_day)
                                            All day
                                        @else
                                            {{ $event->start_at->timezone($timezone)->format('H:i') }}
                                            <br>
                                            {{ $event->end_at->timezone($timezone)->format('H:i') }}
                                        @endif
                                    </div>

                                    {{-- Content column --}}
                                    <div class="min-w-0 flex-1 {{ $past ? 'opacity-40' : '' }}">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $event->subject }}
                                        </p>

                                        @if($event->location)
                                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                                {{ $event->location }}
                                            </p>
                                        @endif

                                        @php $names = $attendeeNames($event); @endphp
                                        @if(!empty($names))
                                            <p class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                                                {{ implode(', ', $names) }}
                                            </p>
                                        @endif

                                        @if($event->is_online_meeting && $event->online_meeting_url)
                                            <a
                                                href="{{ $event->online_meeting_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-0.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                <svg class="h-3 w-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.889L15 14"/><rect x="2" y="7" width="13" height="10" rx="2" ry="2"/>
                                                </svg>
                                                Join online meeting
                                            </a>
                                        @endif

                                        {{-- Linked resource pills --}}
                                        <x-tl.calendar-event-pills />
                                    </div>

                                    {{-- Actions dropdown --}}
                                    <x-tl.calendar-event-actions />

                                    {{-- Status indicator --}}
                                    <span
                                        class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $dotClass }} {{ $past ? 'opacity-40' : '' }}"
                                        aria-label="{{ $event->status->value }}"
                                        role="img"
                                    ></span>
                                </div>
                            @endforeach

                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Later days: collapsible, closed by default --}}
        @if(!empty($laterGroups))
            <div class="divide-y divide-gray-100 dark:divide-gray-800 {{ !empty($prominentGroups) ? 'border-t border-gray-100 dark:border-gray-800' : '' }}">
                @foreach($laterGroups as $dayLabel => $dayEvents)
                    <div x-data="{ open: false }">
                        {{-- Day group header --}}
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-label="{{ $dayLabel }}"
                        >
                            <span>{{ $dayLabel }} ({{ $dayEvents->count() }})</span>
                            <svg
                                class="h-3.5 w-3.5 shrink-0 transition-transform"
                                :class="{ 'rotate-180': open }"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        {{-- Events --}}
                        <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-gray-800">
                            @if($dayEvents->isEmpty())
                                <p class="px-5 py-3 text-sm text-gray-400 dark:text-gray-500">
                                    No events
                                </p>
                            @endif
                            @foreach($dayEvents as $event)
                                @php
                                    $happening = $isHappening($event);
                                    $dotClass  = $statusDotClass($event->status);
                                @endphp

                                <div
                                    x-data="calendarEventActions({{ $event->id }}, {{ $linksJson($event) }}, {{ $canCreateBila($event) ? 'true' : 'false' }})"
                                    class="flex items-start gap-3 px-5 py-3 {{ $happening ? 'border-l-2 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                                    role="row"
                                >
                                    {{-- Time column --}}
                                    <div
                                        class="w-16 shrink-0 text-xs text-gray-500 dark:text-gray-400"
                                        aria-label="{{ $event->is_all_day ? 'All day' : $event->start_at->timezone($timezone)->format('H:i') . ' to ' . $event->end_at->timezone($timezone)->format('H:i') }}"
                                    >
                                        @if($event->is_all_day)
                                            All day
                                        @else
                                            {{ $event->start_at->timezone($timezone)->format('H:i') }}
                                            <br>
                                            {{ $event->end_at->timezone($timezone)->format('H:i') }}
                                        @endif
                                    </div>

                                    {{-- Content column --}}
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $event->subject }}
                                        </p>

                                        @if($event->location)
                                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                                {{ $event->location }}
                                            </p>
                                        @endif

                                        @php $names = $attendeeNames($event); @endphp
                                        @if(!empty($names))
                                            <p class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                                                {{ implode(', ', $names) }}
                                            </p>
                                        @endif

                                        @if($event->is_online_meeting && $event->online_meeting_url)
                                            <a
                                                href="{{ $event->online_meeting_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-0.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                <svg class="h-3 w-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.889L15 14"/><rect x="2" y="7" width="13" height="10" rx="2" ry="2"/>
                                                </svg>
                                                Join online meeting
                                            </a>
                                        @endif

                                        {{-- Linked resource pills --}}
                                        <x-tl.calendar-event-pills />
                                    </div>

                                    {{-- Actions dropdown --}}
                                    <x-tl.calendar-event-actions />

                                    {{-- Status indicator --}}
                                    <span
                                        class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $dotClass }}"
                                        aria-label="{{ $event->status->value }}"
                                        role="img"
                                    ></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</section>
