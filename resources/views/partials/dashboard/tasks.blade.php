<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
            {{ $upcomingTasks->isNotEmpty() ? 'Upcoming tasks' : 'Tasks needing attention' }}
        </h2>
        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
            {{ $todayTasks->count() + $upcomingTasks->count() }}
        </span>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($todayTasks as $task)
            <div class="px-5 py-3">
                <x-tl.task-card :task="$task" :draggable="false" />
            </div>
        @empty
            @if($upcomingTasks->isEmpty())
                <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                    No tasks due today.
                </p>
            @else
                <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                    All clear for today.
                </p>
            @endif
        @endforelse

        @if($upcomingTasks->isNotEmpty())
            <div class="elvish-divider mx-5">
                <span class="elvish-divider-leaf"></span>
            </div>

            @foreach($upcomingTasks as $task)
                <div class="px-5 py-3">
                    <x-tl.task-card :task="$task" :draggable="false" />
                </div>
            @endforeach
        @endif
    </div>
</div>
