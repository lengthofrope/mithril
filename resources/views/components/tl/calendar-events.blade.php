@props(['events', 'isMicrosoftConnected' => false])

@php
    use App\Enums\CalendarEventStatus;

    $now = now();

    /**
     * Return the Tailwind dot color class for a given CalendarEventStatus enum value.
     */
    $statusDotClass = function (CalendarEventStatus $status): string {
        return match ($status) {
            CalendarEventStatus::Busy             => 'bg-blue-500',
            CalendarEventStatus::Tentative        => 'bg-blue-300',
            CalendarEventStatus::OutOfOffice      => 'bg-red-400',
            CalendarEventStatus::Free             => 'bg-green-400',
            CalendarEventStatus::WorkingElsewhere => 'bg-yellow-400',
        };
    };

    /**
     * Return whether an event is currently in progress.
     */
    $isHappening = function (\App\Models\CalendarEvent $event) use ($now): bool {
        return $event->start_at->lte($now) && $event->end_at->gte($now);
    };

    /**
     * Group an event collection by a human-readable day label.
     *
     * @param \Illuminate\Database\Eloquent\Collection $collection
     * @return array<string, \Illuminate\Database\Eloquent\Collection>
     */
    $groupByDay = function (\Illuminate\Database\Eloquent\Collection $collection) use ($now): array {
        $groups = [];

        foreach ($collection as $event) {
            $date = $event->start_at->toDateString();

            if ($event->start_at->isToday()) {
                $label = 'Today';
            } elseif ($event->start_at->isTomorrow()) {
                $label = 'Tomorrow';
            } else {
                $label = $event->start_at->format('l, d F');
            }

            $groups[$label] ??= collect();
            $groups[$label]->push($event);
        }

        return $groups;
    };

    $grouped = $groupByDay($events);
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
    @elseif($events->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
            No events scheduled for the rest of this week.
        </p>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($grouped as $dayLabel => $dayEvents)
                {{-- Day group header --}}
                <div
                    class="bg-gray-50 px-5 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400"
                    role="rowgroup"
                    aria-label="{{ $dayLabel }}"
                >
                    {{ $dayLabel }}
                </div>

                {{-- Events for this day --}}
                @foreach($dayEvents as $event)
                    @php
                        $happening = $isHappening($event);
                        $dotClass  = $statusDotClass($event->status);
                    @endphp

                    <div
                        class="flex items-start gap-3 px-5 py-3 {{ $happening ? 'border-l-2 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                        role="row"
                    >
                        {{-- Time column --}}
                        <div
                            class="w-16 shrink-0 text-xs text-gray-500 dark:text-gray-400"
                            aria-label="{{ $event->is_all_day ? 'All day' : $event->start_at->format('H:i') . ' to ' . $event->end_at->format('H:i') }}"
                        >
                            @if($event->is_all_day)
                                All day
                            @else
                                {{ $event->start_at->format('H:i') }}
                                <br>
                                {{ $event->end_at->format('H:i') }}
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
                        </div>

                        {{-- Status indicator --}}
                        <span
                            class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $dotClass }}"
                            aria-label="{{ $event->status->value }}"
                            role="img"
                        ></span>
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif
</section>
