@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    <div class="space-y-6 max-w-2xl">

        {{-- Task categories --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Task categories</h2>
            </div>

            <div class="p-5">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Categories classify the <strong class="font-medium text-gray-700 dark:text-gray-300">type</strong> of work a task represents, such as "Bug", "HR" or "Improvement". A task can have one category. Categories are shared across all groups and views.
                </p>
                <x-tl.sortable-container
                    modelType="task_category"
                    :endpoint="route('reorder')"
                    containerId="task-categories-list"
                >
                    @forelse($categories as $category)
                        <div
                            data-id="{{ $category->id }}"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                            role="listitem"
                        >
                            <button
                                type="button"
                                class="drag-handle shrink-0 cursor-grab touch-none text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                aria-label="Drag to reorder"
                                tabindex="-1"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                    <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                    <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                </svg>
                            </button>

                            <span class="flex-1 text-sm text-gray-800 dark:text-white/90">
                                {{ $category->name }}
                            </span>

                            <x-tl.confirm-dialog-modal
                                :title="'Delete ' . $category->name . '?'"
                                message="This will remove the category. Tasks using it will become uncategorised."
                                :triggerId="'del-cat-' . $category->id"
                            >
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                        aria-label="Delete category {{ $category->name }}"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="form">
                                    <form
                                        id="confirm-form-del-cat-{{ $category->id }}"
                                        method="POST"
                                        action="{{ route('categories.destroy', $category->id) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </x-slot>
                            </x-tl.confirm-dialog-modal>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400 dark:text-gray-500">
                            No categories yet.
                        </p>
                    @endforelse
                </x-tl.sortable-container>

                {{-- Add category form --}}
                <form method="POST" action="{{ route('categories.store') }}" class="mt-4 flex items-center gap-3">
                    @csrf
                    <label for="new-category-name" class="sr-only">New category name</label>
                    <input
                        id="new-category-name"
                        type="text"
                        name="name"
                        placeholder="New category name…"
                        required
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add
                    </button>
                </form>
            </div>
        </div>

        {{-- Task groups --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Task groups</h2>
            </div>

            <div class="p-5">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Groups bundle tasks into <strong class="font-medium text-gray-700 dark:text-gray-300">projects or workstreams</strong>, such as "Sprint 12", "Q2 Goals" or "Onboarding". Tasks can be dragged between groups in the list view. A task without a group appears under "Ungrouped".
                </p>
                <x-tl.sortable-container
                    modelType="task_group"
                    :endpoint="route('reorder')"
                    containerId="task-groups-list"
                >
                    @forelse($groups as $group)
                        <div
                            data-id="{{ $group->id }}"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                            role="listitem"
                        >
                            <button
                                type="button"
                                class="drag-handle shrink-0 cursor-grab touch-none text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                aria-label="Drag to reorder"
                                tabindex="-1"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                    <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                    <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                </svg>
                            </button>

                            @if($group->color)
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    style="background-color: {{ $group->color }}"
                                    aria-hidden="true"
                                ></span>
                            @endif

                            <span class="flex-1 text-sm text-gray-800 dark:text-white/90">
                                {{ $group->name }}
                            </span>

                            <x-tl.confirm-dialog-modal
                                :title="'Delete ' . $group->name . '?'"
                                message="This will remove the group. Tasks in this group will become ungrouped."
                                :triggerId="'del-group-' . $group->id"
                            >
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                        aria-label="Delete group {{ $group->name }}"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                        </svg>
                                    </button>
                                </x-slot>
                                <x-slot name="form">
                                    <form
                                        id="confirm-form-del-group-{{ $group->id }}"
                                        method="POST"
                                        action="{{ route('task-groups.destroy', $group->id) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </x-slot>
                            </x-tl.confirm-dialog-modal>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400 dark:text-gray-500">
                            No groups yet.
                        </p>
                    @endforelse
                </x-tl.sortable-container>

                {{-- Add group form --}}
                <form method="POST" action="{{ route('task-groups.store') }}" class="mt-4 flex items-center gap-3">
                    @csrf
                    <label for="new-group-color" class="sr-only">Group color</label>
                    <input
                        id="new-group-color"
                        type="color"
                        name="color"
                        value="#3b82f6"
                        class="h-9 w-9 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-900"
                    >
                    <label for="new-group-name" class="sr-only">New group name</label>
                    <input
                        id="new-group-name"
                        type="text"
                        name="name"
                        placeholder="New group name…"
                        required
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add
                    </button>
                </form>
            </div>
        </div>

    </div>
@endsection
