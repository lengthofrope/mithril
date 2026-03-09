{{--
    Partial: notes-list
    Variables:
        $notes — collection of Note models (pinned first)
--}}

@if($notes->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
        <p class="text-sm text-gray-400 dark:text-gray-500">No notes found.</p>
    </div>
@else
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($notes as $note)
            <div
                class="group flex flex-col rounded-xl border bg-white transition hover:border-gray-300 hover:shadow-sm dark:bg-white/[0.03] dark:hover:border-gray-700 {{ $note->is_pinned ? 'border-yellow-300 dark:border-yellow-700/50' : 'border-gray-200 dark:border-gray-800' }}"
                role="listitem"
                data-href="{{ route('notes.show', $note) }}"
                x-data
                x-on:dblclick="if (!window.getSelection()?.toString().trim()) window.location.href = $el.dataset.href"
            >
                {{-- Header --}}
                <div class="flex items-start justify-between gap-2 p-4 pb-2">
                    <div class="flex items-center gap-1.5 min-w-0">
                        @if($note->is_pinned)
                            <svg class="h-3.5 w-3.5 shrink-0 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-label="Pinned">
                                <path d="M17.5 2.5l4 4-7 7 1 5-7-7-5.5 5.5L2 16l5.5-5.5-7-7 5 1 7-7z"/>
                            </svg>
                        @endif
                        <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $note->title }}
                        </h3>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ \Carbon\Carbon::parse($note->updated_at)->format('d M') }}
                        </span>
                        <a
                            href="{{ route('notes.show', $note) }}"
                            class="rounded p-1 text-gray-400 opacity-0 transition hover:text-blue-600 group-hover:opacity-100 dark:hover:text-blue-400"
                            title="Edit"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Content preview --}}
                <div class="flex-1 px-4 pb-3">
                    <p class="line-clamp-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ $note->content ? strip_tags(Str::markdown($note->content)) : 'No content yet…' }}
                    </p>
                </div>

                {{-- Footer: member + tags --}}
                @if($note->teamMember || $note->tags->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 px-4 py-2 dark:border-gray-800">
                        @if($note->teamMember)
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $note->teamMember->name }}
                            </span>
                        @endif

                        @foreach($note->tags as $tag)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                {{ $tag->tag }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
