@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    {{-- Filter bar --}}
    <div class="mb-6">
        <x-tl.filter-bar
            :endpoint="route('tasks.kanban')"
            results-selector="#kanban-results"
            :filters="[
                ['field' => 'search', 'type' => 'search', 'label' => 'Search'],
                ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions],
                ['field' => 'priority', 'type' => 'select', 'label' => 'Priority', 'options' => [
                    ['value' => 'urgent', 'label' => 'Urgent'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'low', 'label' => 'Low'],
                ]],
                ['field' => 'is_private', 'type' => 'boolean', 'label' => 'Private only'],
                ['field' => 'overdue', 'type' => 'boolean', 'label' => 'Overdue'],
                ['field' => 'is_recurring', 'type' => 'boolean', 'label' => 'Recurring'],
            ]"
        />
    </div>

    {{-- View switcher + new task --}}
    <div class="mb-4 flex items-center justify-end gap-2">
        <a
            href="{{ route('tasks.index') }}"
            class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
            aria-label="Switch to list view"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            List view
        </a>

        @include('partials.task-create-modal')
    </div>

    {{-- Kanban board --}}
    <div id="kanban-results">
        @include('partials.kanban-board', ['tasks' => $tasks, 'taskGroups' => $groups])
    </div>
@endsection
