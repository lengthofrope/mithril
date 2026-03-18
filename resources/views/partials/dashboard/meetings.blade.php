<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
            {{ $upcomingMeetings->isNotEmpty() ? 'Upcoming meetings' : 'Meetings today' }}
        </h2>
        <span class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-600 dark:bg-purple-500/15 dark:text-purple-400">
            {{ $todayMeetings->count() + $upcomingMeetings->count() }}
        </span>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($todayMeetings as $meeting)
            <a
                href="{{ route('meetings.show', $meeting->id) }}"
                class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
            >
                @if($meeting->attendees->first())
                    <x-tl.team-member-avatar :member="$meeting->attendees->first()" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                            {{ $meeting->attendees->first()->name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $meeting->scheduled_at->format('d M Y') }}
                        </p>
                    </div>
                @else
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                            Meeting #{{ $meeting->id }}
                        </p>
                    </div>
                @endif
                <svg class="h-4 w-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @empty
            @if($upcomingMeetings->isEmpty())
                <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                    No meetings scheduled today.
                </p>
            @else
                <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                    All clear for today.
                </p>
            @endif
        @endforelse

        @if($upcomingMeetings->isNotEmpty())
            <div class="elvish-divider mx-5">
                <span class="elvish-divider-leaf"></span>
            </div>

            @foreach($upcomingMeetings as $meeting)
                <a
                    href="{{ route('meetings.show', $meeting->id) }}"
                    class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                >
                    @if($meeting->attendees->first())
                        <x-tl.team-member-avatar :member="$meeting->attendees->first()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $meeting->attendees->first()->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $meeting->scheduled_at->format('d M Y') }}
                            </p>
                        </div>
                    @else
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                Meeting #{{ $meeting->id }}
                            </p>
                        </div>
                    @endif
                    <svg class="h-4 w-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            @endforeach
        @endif
    </div>
</div>
