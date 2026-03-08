@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Teams" />

    {{-- Toolbar --}}
    <div class="mb-6 flex items-center justify-end" x-data="{ addOpen: false }">
        <button
            type="button"
            x-on:click="addOpen = !addOpen"
            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New team
        </button>

        <div
            x-show="addOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            x-on:keydown.escape.window="addOpen = false"
        >
            <div
                class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                x-on:click.outside="addOpen = false"
            >
                <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Create a new team</h2>
                <form method="POST" action="{{ route('teams.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="new-team-name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input
                            id="new-team-name"
                            type="text"
                            name="name"
                            placeholder="Team name…"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="mb-3">
                        <label for="new-team-description" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea
                            id="new-team-description"
                            name="description"
                            rows="2"
                            placeholder="Optional description…"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        ></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="new-team-color" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <input
                            id="new-team-color"
                            type="color"
                            name="color"
                            value="#3b82f6"
                            class="h-10 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-800"
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

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($teams as $team)
            <a
                href="{{ route('teams.show', $team->id) }}"
                class="group relative flex flex-col gap-4 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.06] dark:hover:border-gray-700"
            >
                {{-- Color accent bar --}}
                <div
                    class="absolute left-0 top-0 h-full w-1.5 rounded-l-xl"
                    style="background-color: {{ $team->color ?? '#3b82f6' }}"
                    aria-hidden="true"
                ></div>

                <div class="pl-3">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $team->name }}
                        </h2>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </div>

                    @if($team->description)
                        <p class="mt-1 text-sm text-gray-500 line-clamp-2 dark:text-gray-400">
                            {{ $team->description }}
                        </p>
                    @endif

                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <span>{{ $team->members_count ?? $team->members->count() }} member(s)</span>
                        </div>

                        <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            <span>{{ $team->open_tasks_count ?? 0 }} open task(s)</span>
                        </div>
                    </div>

                    @if(isset($team->members) && $team->members->count() > 0)
                        <div class="mt-3 flex -space-x-2">
                            @foreach($team->members->take(5) as $member)
                                <x-tl.team-member-avatar :member="$member" size="sm" />
                            @endforeach
                            @if($team->members->count() > 5)
                                <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-gray-200 text-xs font-medium text-gray-600 dark:border-gray-900 dark:bg-gray-700 dark:text-gray-300">
                                    +{{ $team->members->count() - 5 }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">No teams yet.</p>
            </div>
        @endforelse
    </div>
@endsection
