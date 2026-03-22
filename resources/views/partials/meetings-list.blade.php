{{-- Upcoming meetings --}}
<div class="mb-8">
    <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Upcoming</h2>

    @if($upcomingMeetings->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($upcomingMeetings as $meeting)
                <a
                    href="{{ route('meetings.show', $meeting->id) }}"
                    class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
                >
                    @if($meeting->attendees->first())
                        <x-tl.team-member-avatar :member="$meeting->attendees->first()" size="md" />
                    @else
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M16 21V19C16 16.7909 13.3137 15 10 15C6.68629 15 4 16.7909 4 19V21M10 12C7.79086 12 6 10.2091 6 8C6 5.79086 7.79086 4 10 4C12.2091 4 14 5.79086 14 8C14 10.2091 12.2091 12 10 12Z"/>
                            </svg>
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $meeting->title }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $meeting->scheduled_at->format('d F Y') }}
                            @if($meeting->attendees->first())
                                &middot; {{ $meeting->attendees->first()->name }}
                            @endif
                        </p>
                    </div>

                    @php
                        $typeBadge = match($meeting->type->value) {
                            'one_on_one' => ['label' => '1:1', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
                            'team' => ['label' => 'Team', 'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'],
                            default => ['label' => 'Other', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
                        };
                    @endphp
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $typeBadge['class'] }}">
                        {{ $typeBadge['label'] }}
                    </span>

                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            @endforeach
        </div>
    @else
        <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400 dark:border-gray-700 dark:text-gray-500">
            No upcoming meetings scheduled.
        </p>
    @endif
</div>

{{-- Past meetings --}}
@if($pastMeetings->isNotEmpty())
    <div>
        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Past meetings</h2>

        <div class="space-y-2">
            @foreach($pastMeetings as $meeting)
                <a
                    href="{{ route('meetings.show', $meeting->id) }}"
                    class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
                >
                    @if($meeting->attendees->first())
                        <x-tl.team-member-avatar :member="$meeting->attendees->first()" size="sm" />
                    @endif

                    <div class="min-w-0 flex-1">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $meeting->title }}</span>
                        @if($meeting->attendees->first())
                            <span class="text-xs text-gray-400 dark:text-gray-500">&middot; {{ $meeting->attendees->first()->name }}</span>
                        @endif
                    </div>

                    @php
                        $typeBadge = match($meeting->type->value) {
                            'one_on_one' => ['label' => '1:1', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
                            'team' => ['label' => 'Team', 'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'],
                            default => ['label' => 'Other', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
                        };
                    @endphp
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $typeBadge['class'] }}">
                        {{ $typeBadge['label'] }}
                    </span>

                    @if($meeting->is_done)
                        <span class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
                            Done
                        </span>
                    @endif

                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                        {{ $meeting->scheduled_at->format('d M Y') }}
                    </span>

                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            @endforeach
        </div>
    </div>
@endif
