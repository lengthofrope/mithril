@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Tasks" />

    {{-- Filter bar --}}
    <div class="mb-6">
        <x-tl.filter-bar
            :endpoint="route('tasks.index')"
            results-selector="#tasks-results"
            :filters="[
                ['field' => 'search', 'type' => 'search', 'label' => 'Search'],
                ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
                ['field' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => $categoryOptions],
                ['field' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                    ['value' => 'open', 'label' => 'Open'],
                    ['value' => 'in_progress', 'label' => 'In Progress'],
                    ['value' => 'waiting', 'label' => 'Waiting'],
                    ['value' => 'done', 'label' => 'Done'],
                ]],
                ['field' => 'priority', 'type' => 'select', 'label' => 'Priority', 'options' => [
                    ['value' => 'urgent', 'label' => 'Urgent'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'low', 'label' => 'Low'],
                ]],
                ['field' => 'task_group_id', 'type' => 'select', 'label' => 'Group', 'options' => $groupOptions],
                ['field' => 'is_private', 'type' => 'boolean', 'label' => 'Private only'],
            ]"
        />
    </div>

    {{-- Toolbar --}}
    <div x-data="{ showAddGroup: false }" class="mb-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                {{-- Toggle completed --}}
                <button
                    type="button"
                    x-on:click="$store.taskList.showCompleted = !$store.taskList.showCompleted"
                    x-bind:aria-pressed="$store.taskList.showCompleted"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    <span x-text="$store.taskList.showCompleted ? 'Hide completed' : 'Show completed'">Show completed</span>
                </button>

                {{-- Add group toggle --}}
                <button
                    type="button"
                    x-on:click="showAddGroup = !showAddGroup"
                    x-bind:aria-pressed="showAddGroup"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                    </svg>
                    Add group
                </button>
            </div>

            <div class="flex items-center gap-2">
                {{-- View toggle --}}
                <a
                    href="{{ route('tasks.kanban') }}"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                    aria-label="Switch to kanban view"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="10" rx="1"/>
                    </svg>
                    Kanban
                </a>

                @include('partials.task-create-modal')
            </div>
        </div>

        {{-- Inline add group form --}}
        <div
            x-show="showAddGroup"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
        >
            <form method="POST" action="{{ route('task-groups.store') }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf
                <label for="toolbar-group-color" class="sr-only">Group color</label>
                <input
                    id="toolbar-group-color"
                    type="color"
                    name="color"
                    value="#3b82f6"
                    class="h-9 w-9 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-900"
                >
                <label for="toolbar-group-name" class="sr-only">New group name</label>
                <input
                    id="toolbar-group-name"
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
                <button
                    type="button"
                    x-on:click="showAddGroup = false"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                >
                    Cancel
                </button>
            </form>
        </div>
    </div>

    {{-- Bulk actions bar (shown when tasks are selected) --}}
    <div
        x-data="{
            selectedIds: [],
            toggleTask(id) {
                const idx = this.selectedIds.indexOf(id);
                if (idx === -1) {
                    this.selectedIds.push(id);
                } else {
                    this.selectedIds.splice(idx, 1);
                }
            },
            clearSelection() {
                this.selectedIds = [];
            },
        }"
        id="task-list-wrapper"
    >
        <div
            x-show="selectedIds.length > 0"
            x-cloak
            class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900/50 dark:bg-blue-500/10"
            aria-live="polite"
        >
            <span class="text-sm font-medium text-blue-700 dark:text-blue-400">
                <span x-text="selectedIds.length"></span> task(s) selected
            </span>

            <form method="POST" action="{{ route('tasks.bulk-update') }}" id="bulk-form">
                @csrf
                @method('PATCH')
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <input type="hidden" name="action" id="bulk-action-input">

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        x-on:click="document.getElementById('bulk-action-input').value = 'mark_done'; document.getElementById('bulk-form').submit()"
                        class="rounded-lg border border-green-300 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-100 dark:border-green-700/50 dark:bg-green-500/10 dark:text-green-400"
                    >
                        Mark done
                    </button>

                    <button
                        type="button"
                        x-on:click="document.getElementById('bulk-action-input').value = 'delete'; document.getElementById('bulk-form').submit()"
                        class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400"
                    >
                        Delete
                    </button>
                </div>
            </form>

            <button
                type="button"
                x-on:click="clearSelection()"
                class="ml-auto text-xs text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
            >
                Clear selection
            </button>
        </div>

        {{-- Results container --}}
        <div id="tasks-results">
            @include('partials.tasks-list', ['taskGroups' => $taskGroups])
        </div>
    </div>
@endsection
