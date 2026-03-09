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
                x-data="Object.assign(
                    markdownEditor({ field: 'content' }),
                    autoSaveField({ endpoint: '{{ route('notes.update', ['note' => '__ID__']) }}'.replace('__ID__', '{{ $note->id }}'), field: 'content' })
                )"
                x-init="content = @js($note->content ?? ''); value = content;"
                class="flex flex-col rounded-xl border bg-white dark:bg-white/[0.03] {{ $note->is_pinned ? 'border-yellow-300 dark:border-yellow-700/50' : 'border-gray-200 dark:border-gray-800' }}"
            >
                {{-- Note header --}}
                <div class="flex items-start justify-between gap-2 p-4 pb-2">
                    <div class="flex items-center gap-1.5 min-w-0">
                        @if($note->is_pinned)
                            <svg class="h-3.5 w-3.5 shrink-0 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-label="Pinned">
                                <path d="M17.5 2.5l4 4-7 7 1 5-7-7-5.5 5.5L2 16l5.5-5.5-7-7 5 1 7-7z"/>
                            </svg>
                        @endif
                        <a
                            href="{{ route('notes.show', $note) }}"
                            class="truncate text-sm font-semibold text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                        >
                            {{ $note->title }}
                        </a>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ \Carbon\Carbon::parse($note->updated_at)->format('d M') }}
                        </span>
                        <a
                            href="{{ route('notes.show', $note) }}"
                            class="text-gray-400 transition hover:text-blue-600 dark:text-gray-500 dark:hover:text-blue-400"
                            aria-label="Open {{ $note->title }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Expandable editor --}}
                <div x-data="{ editing: false }" class="flex-1 px-4 pb-4">
                    <div
                        x-show="!editing"
                        x-on:click="editing = true"
                        class="cursor-pointer"
                    >
                        <p class="line-clamp-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $note->content ? strip_tags(Str::markdown($note->content)) : 'Click to add content…' }}
                        </p>
                    </div>

                    <div x-show="editing" x-cloak>
                        <div class="mb-2 flex items-center gap-1">
                            <button
                                type="button"
                                x-on:click="isPreview = false"
                                x-bind:class="!isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                                class="rounded px-2 py-0.5 text-xs font-medium transition"
                            >
                                Write
                            </button>
                            <button
                                type="button"
                                x-on:click="togglePreview()"
                                x-bind:class="isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                                class="rounded px-2 py-0.5 text-xs font-medium transition"
                            >
                                Preview
                            </button>
                        </div>

                        <div x-show="!isPreview">
                            <label for="note-content-{{ $note->id }}" class="sr-only">Note content</label>
                            <textarea
                                id="note-content-{{ $note->id }}"
                                name="content"
                                x-model="content"
                                x-on:input="value = content"
                                rows="6"
                                placeholder="Write your note in Markdown…"
                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 font-mono text-xs text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                            ></textarea>
                        </div>

                        <div
                            x-show="isPreview"
                            x-cloak
                            x-html="preview"
                            class="prose prose-xs max-w-none min-h-16 text-gray-700 dark:prose-invert dark:text-gray-300"
                        ></div>

                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <div class="flex h-4 items-center" aria-live="polite">
                                <span x-show="status === 'saving'" x-cloak class="text-xs text-gray-400">Saving…</span>
                                <span x-show="status === 'saved'" x-cloak class="text-xs text-green-600 dark:text-green-400">Saved</span>
                                <span x-show="status === 'error'" x-cloak class="text-xs text-red-600">Failed</span>
                            </div>
                            <button
                                type="button"
                                x-on:click="editing = false"
                                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Tags --}}
                @if($note->tags->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1 border-t border-gray-100 px-4 py-2 dark:border-gray-800">
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
