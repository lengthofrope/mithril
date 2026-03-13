@props(['task', 'hideWhenDone' => false, 'draggable' => true])

@php
    $statusValue = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;
@endphp

<div
    data-id="{{ $task->id }}"
    data-status="{{ $statusValue }}"
    data-href="{{ route('tasks.show', $task->id) }}"
    role="listitem"
    @if($hideWhenDone)
        x-data
        x-show="'{{ $statusValue }}' !== 'done' || $store.taskList.showCompleted"
    @endif
    class="group relative flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
>
    @if($draggable)
        {{-- Drag handle --}}
        <button
            type="button"
            class="drag-handle mt-0.5 shrink-0 cursor-grab touch-none text-gray-300 transition hover:text-gray-500 active:cursor-grabbing dark:text-gray-600 dark:hover:text-gray-400"
            aria-label="Drag to reorder"
            tabindex="-1"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
            </svg>
        </button>
    @endif

    <div class="min-w-0 flex-1">
        @if($task->is_private)
            <x-tl.privacy-shield :isPrivate="true">
                <div class="flex flex-wrap items-start gap-2">
                    <p class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90">
                        {{ $task->title }}
                    </p>
                </div>
            </x-tl.privacy-shield>
        @else
            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                {{ $task->title }}
            </p>
        @endif

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-tl.inline-select-pill
                :value="$task->priority"
                :options="[
                    'urgent' => 'Urgent',
                    'high' => 'High',
                    'normal' => 'Normal',
                    'low' => 'Low',
                ]"
                :color-map="[
                    'urgent' => 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400',
                    'high' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
                    'normal' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
                    'low' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
                ]"
                endpoint="/api/v1/tasks/{{ $task->id }}"
                field="priority"
            />
            <x-tl.inline-select-pill
                :value="$task->status"
                :options="[
                    'open' => 'Open',
                    'in_progress' => 'In Progress',
                    'waiting' => 'Waiting',
                    'done' => 'Done',
                ]"
                :color-map="[
                    'open' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
                    'in_progress' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
                    'waiting' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
                    'done' => 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500',
                ]"
                endpoint="/api/v1/tasks/{{ $task->id }}"
                field="status"
            />

            @if($task->deadline)
                @php
                    $deadlineDate = \Carbon\Carbon::parse($task->deadline)->startOfDay();
                    $deadlineColorClass = match(true) {
                        $deadlineDate->isPast() && !$deadlineDate->isToday() => 'text-red-600 dark:text-red-400',
                        $deadlineDate->isToday() => 'text-orange-600 dark:text-orange-400',
                        default => 'text-gray-500 dark:text-gray-400',
                    };
                    $deadlineLabel = match(true) {
                        $deadlineDate->isToday() => 'Today',
                        $deadlineDate->isTomorrow() => 'Tomorrow',
                        $deadlineDate->isPast() => 'Overdue · ' . $deadlineDate->format('d M Y'),
                        default => $deadlineDate->format('d M Y'),
                    };
                @endphp
                <span class="flex items-center gap-1 text-xs {{ $deadlineColorClass }}">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    {{ $deadlineLabel }}
                </span>
            @endif

            @if($task->is_recurring)
                <span class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400" title="Recurring {{ $task->recurrence_interval?->value ?? '' }}">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                    </svg>
                </span>
            @endif

        </div>

        @if($task->teamMember || $task->team)
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ collect([$task->teamMember?->name, $task->team?->name])->filter()->implode(' · ') }}
            </p>
        @endif
    </div>

    <a
        href="{{ route('tasks.show', $task->id) }}"
        class="shrink-0 rounded-lg p-1.5 text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 group-hover:opacity-100 dark:hover:bg-gray-800 dark:hover:text-gray-300"
        aria-label="View task {{ $task->title }}"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
    </a>
</div>
