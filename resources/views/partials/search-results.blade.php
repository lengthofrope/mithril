{{--
    Partial: search-results
    Variables:
        $query   — string, the search query
        $results — associative array:
            'tasks'       => collection
            'follow_ups'  => collection
            'notes'       => collection
            'members'     => collection
--}}

@php
    $totalCount = collect($results)->flatten(1)->count();
@endphp

@if($totalCount === 0)
    <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
        <p class="text-sm text-gray-400 dark:text-gray-500">
            No results found for <span class="font-medium text-gray-600 dark:text-gray-300">"{{ $query }}"</span>
        </p>
    </div>
@else
    <div class="space-y-6">

        {{-- Tasks --}}
        @if(isset($results['tasks']) && $results['tasks']->isNotEmpty())
            <section aria-label="Task results">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Tasks ({{ $results['tasks']->count() }})
                </h2>
                <div class="space-y-2" role="list">
                    @foreach($results['tasks'] as $task)
                        <x-tl.task-card :task="$task" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Follow-ups --}}
        @if(isset($results['follow_ups']) && $results['follow_ups']->isNotEmpty())
            <section aria-label="Follow-up results">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Follow-ups ({{ $results['follow_ups']->count() }})
                </h2>
                <div class="space-y-2" role="list">
                    @foreach($results['follow_ups'] as $followUp)
                        <x-tl.follow-up-card :followUp="$followUp" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Notes --}}
        @if(isset($results['notes']) && $results['notes']->isNotEmpty())
            <section aria-label="Note results">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Notes ({{ $results['notes']->count() }})
                </h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($results['notes'] as $note)
                        <a
                            href="{{ route('notes.show', $note->id) }}"
                            class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
                        >
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $note->title }}</h3>
                            @if($note->content)
                                <p class="line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ strip_tags($note->content) }}
                                </p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Members --}}
        @if(isset($results['members']) && $results['members']->isNotEmpty())
            <section aria-label="Team member results">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Members ({{ $results['members']->count() }})
                </h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($results['members'] as $member)
                        <a
                            href="{{ route('teams.member', [$member->team_id, $member->id]) }}"
                            class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
                        >
                            <x-tl.team-member-avatar :member="$member" size="md" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $member->name }}
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $member->role }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
@endif
