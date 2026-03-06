@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notes" />

    {{-- Toolbar --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        {{-- Search --}}
        <div class="flex flex-1 flex-wrap items-end gap-3">
            <div
                x-data="filterManager({
                    endpoint: '{{ route('notes.index') }}',
                    resultsSelector: '#notes-results',
                    filters: [
                        { field: 'search', type: 'search', label: 'Search notes' },
                    ],
                })"
                class="flex flex-1 flex-wrap items-end gap-3"
            >
                <div class="relative flex-1 min-w-48">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                    </span>
                    <label for="notes-search" class="sr-only">Search notes</label>
                    <input
                        id="notes-search"
                        type="search"
                        x-model.debounce.500ms="filterState['search']"
                        placeholder="Search notes…"
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                </div>
            </div>

            {{-- Tag filters --}}
            @if($allTags->isNotEmpty())
                <div
                    x-data="{ selectedTag: '' }"
                    class="flex flex-wrap items-center gap-1.5"
                    aria-label="Filter by tag"
                >
                    <button
                        type="button"
                        x-on:click="selectedTag = ''"
                        x-bind:class="selectedTag === '' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                    >
                        All
                    </button>

                    @foreach($allTags as $tag)
                        <button
                            type="button"
                            x-on:click="selectedTag = '{{ $tag }}'"
                            x-bind:class="selectedTag === '{{ $tag }}' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                        >
                            {{ $tag }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- New note --}}
        <div x-data="{ addOpen: false }">
            <button
                type="button"
                x-on:click="addOpen = !addOpen"
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New note
            </button>

            <div
                x-show="addOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-500/10"
            >
                <form method="POST" action="{{ route('notes.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="new-note-title" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Title</label>
                        <input
                            id="new-note-title"
                            type="text"
                            name="title"
                            placeholder="Note title…"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                        >
                            Create
                        </button>
                        <button
                            type="button"
                            x-on:click="addOpen = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Notes grid --}}
    <div id="notes-results">
        @include('partials.notes-list', ['notes' => $notes])
    </div>
@endsection
