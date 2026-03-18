{{--
    Partial: kanban-board
    Variables:
        $tasks — collection of Task models
        $taskGroups — collection of TaskGroup models (optional)
--}}

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
                    class="flex flex-col gap-2 border-t border-gray-100 p-3 min-h-32 max-h-[calc(100vh-16rem)] overflow-y-auto dark:border-gray-800"
                    role="list"
                    aria-label="{{ $column['label'] }} tasks"
                >
                    @foreach($columnTasks as $task)
                        <x-tl.task-card :task="$task" :taskGroups="$taskGroups ?? null" />
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
