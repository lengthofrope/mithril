@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Tasks — Kanban" />

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
        <div
            x-data="sortableKanban({
                containerSelector: '#kanban-board',
                modelType: 'task',
                endpoint: '{{ route('tasks.move') }}',
                reorderEndpoint: '{{ route('reorder') }}',
                statusField: 'status',
            })"
        >
            <div
                id="kanban-board"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Task kanban board"
            >
                @foreach([
                    ['status' => 'open',        'label' => 'Open',        'color' => 'blue'],
                    ['status' => 'in_progress',  'label' => 'In Progress', 'color' => 'yellow'],
                    ['status' => 'waiting',      'label' => 'Waiting',     'color' => 'orange'],
                    ['status' => 'done',         'label' => 'Done',        'color' => 'green'],
                ] as $column)
                    @php
                        $columnTasks = $tasks->where('status', $column['status']);
                        $statusColorMap = [
                            'blue'   => 'bg-blue-500',
                            'yellow' => 'bg-yellow-500',
                            'orange' => 'bg-orange-500',
                            'green'  => 'bg-green-500',
                        ];
                        $dotColor = $statusColorMap[$column['color']];
                    @endphp

                    <div class="flex flex-col rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block h-3 w-3 rounded-full {{ $dotColor }}"
                                    aria-hidden="true"
                                ></span>
                                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                    {{ $column['label'] }}
                                </h2>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    {{ $columnTasks->count() }}
                                </span>
                            </div>
                        </div>

                        <div
                            data-kanban-status="{{ $column['status'] }}"
                            class="flex flex-col gap-2 border-t border-gray-100 p-3 min-h-32 dark:border-gray-800"
                            role="list"
                            aria-label="{{ $column['label'] }} tasks"
                        >
                            @foreach($columnTasks as $task)
                                <x-tl.task-card :task="$task" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div
                x-show="hasMoveError"
                x-cloak
                class="mt-4 text-sm text-red-600 dark:text-red-400"
                aria-live="assertive"
            >
                Failed to move task. Please try again.
            </div>
        </div>
    </div>
@endsection
