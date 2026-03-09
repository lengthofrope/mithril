@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    {{-- Team header --}}
    <div
        x-data="{ editOpen: false, deleteOpen: false }"
        class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div
            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-white text-lg font-bold"
            style="background-color: {{ $team->color ?? '#3b82f6' }}"
            aria-hidden="true"
        >
            {{ strtoupper(mb_substr($team->name, 0, 1)) }}
        </div>

        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $team->name }}
            </h1>
            @if($team->description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $team->description }}
                </p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $team->members->count() }} member(s)
            </span>

            <button
                type="button"
                x-on:click="editOpen = true"
                class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5"
                title="Edit team"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
            </button>

            <button
                type="button"
                x-on:click="deleteOpen = true"
                class="flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-500/10"
                title="Delete team"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete
            </button>
        </div>

        {{-- Edit team modal --}}
        <div
            x-show="editOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            x-on:keydown.escape.window="editOpen = false"
        >
            <div
                class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                x-on:click.outside="editOpen = false"
            >
                <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Edit team</h2>
                <form method="POST" action="{{ route('teams.update', $team->id) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label for="edit-team-name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input
                            id="edit-team-name"
                            type="text"
                            name="name"
                            value="{{ $team->name }}"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="mb-3">
                        <label for="edit-team-description" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea
                            id="edit-team-description"
                            name="description"
                            rows="2"
                            placeholder="Optional description…"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >{{ $team->description }}</textarea>
                    </div>
                    <div class="mb-4">
                        <label for="edit-team-color" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <input
                            id="edit-team-color"
                            type="color"
                            name="color"
                            value="{{ $team->color ?? '#3b82f6' }}"
                            class="h-10 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-800"
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            x-on:click="editOpen = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete team confirmation modal --}}
        <div
            x-show="deleteOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            x-on:keydown.escape.window="deleteOpen = false"
        >
            <div
                class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                x-on:click.outside="deleteOpen = false"
            >
                <h2 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Delete team</h2>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete <strong>{{ $team->name }}</strong>?
                    This will also remove all {{ $team->members->count() }} member(s).
                </p>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('teams.destroy', $team->id) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </form>
                    <button
                        type="button"
                        x-on:click="deleteOpen = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Members header + add button --}}
    <div class="mb-4 flex items-center justify-between" x-data="{ addMemberOpen: false }">
        <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Members</h2>

        <button
            type="button"
            x-on:click="addMemberOpen = !addMemberOpen"
            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add member
        </button>

        {{-- Add member modal --}}
        <div
            x-show="addMemberOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            x-on:keydown.escape.window="addMemberOpen = false"
        >
            <div
                class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                x-on:click.outside="addMemberOpen = false"
            >
                <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Add team member</h2>
                <form method="POST" action="{{ route('teams.members.store', $team->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="new-member-name" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input
                            id="new-member-name"
                            type="text"
                            name="name"
                            placeholder="Full name…"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="mb-3">
                        <label for="new-member-role" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Role / function</label>
                        <input
                            id="new-member-role"
                            type="text"
                            name="role"
                            placeholder="e.g. Senior Developer…"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="mb-4">
                        <label for="new-member-email" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input
                            id="new-member-email"
                            type="email"
                            name="email"
                            placeholder="name@company.com"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                        >
                            Add
                        </button>
                        <button
                            type="button"
                            x-on:click="addMemberOpen = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($team->members->sortBy('sort_order') as $member)
            @php
                $statusColorMap = [
                    'available'           => 'bg-green-500',
                    'absent'              => 'bg-red-400',
                    'partially_available' => 'bg-yellow-500',
                ];
                $statusLabelMap = [
                    'available'           => 'Available',
                    'absent'              => 'Absent',
                    'partially_available' => 'Partially available',
                ];
                $statusKey = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                $statusColor = $statusColorMap[$statusKey] ?? 'bg-gray-400';
                $statusLabel = $statusLabelMap[$statusKey] ?? ucfirst($statusKey);
            @endphp

            <a
                href="{{ route('teams.member', $member->id) }}"
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
            >
                <div class="relative shrink-0">
                    <x-tl.team-member-avatar :member="$member" size="lg" />
                    <span
                        class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white dark:border-gray-900 {{ $statusColor }}"
                        title="{{ $statusLabel }}"
                    ></span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $member->name }}
                    </p>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $member->role }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ $statusLabel }}
                    </p>
                </div>

                <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">No members in this team yet.</p>
            </div>
        @endforelse
    </div>
@endsection
