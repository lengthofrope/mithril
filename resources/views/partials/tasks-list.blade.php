{{--
    Partial: tasks-list
    Variables:
        $taskGroups — collection of TaskGroup models, each with ->tasks relation loaded
        $ungroupedTasks — collection of tasks without a task group (optional)
--}}

@php
    $hasGroupedTasks = $taskGroups->contains(fn ($group) => $group->tasks->isNotEmpty());
    $hasUngrouped = isset($ungroupedTasks) && $ungroupedTasks->isNotEmpty();
@endphp

@if(!$hasGroupedTasks && !$hasUngrouped)
    <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
        <p class="text-sm text-gray-400 dark:text-gray-500">No tasks found.</p>
    </div>
@else
    <div class="space-y-4">
        @foreach($taskGroups as $group)
            @if($group->tasks->isNotEmpty())
                <div
                    x-data="{ collapsed: false }"
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    {{-- Group header --}}
                    <div class="flex w-full items-center justify-between px-5 py-3">
                        <button
                            type="button"
                            x-on:click="collapsed = !collapsed"
                            x-bind:aria-expanded="!collapsed"
                            class="flex flex-1 items-center gap-2 text-left"
                        >
                            @if($group->color)
                                <span
                                    class="inline-block h-3 w-3 rounded-full"
                                    style="background-color: {{ $group->color }}"
                                    aria-hidden="true"
                                ></span>
                            @endif
                            <span class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ $group->name }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                {{ $group->tasks->count() }}
                            </span>
                        </button>

                        <div class="flex items-center gap-1" x-on:click.stop>
                            <x-tl.confirm-dialog-modal
                                :title="'Delete ' . $group->name . '?'"
                                message="This will remove the group. Tasks in this group will become ungrouped."
                                :triggerId="'del-tl-group-' . $group->id"
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
                                        id="confirm-form-del-tl-group-{{ $group->id }}"
                                        method="POST"
                                        action="{{ route('task-groups.destroy', $group->id) }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </x-slot>
                            </x-tl.confirm-dialog-modal>

                            <svg
                                class="h-4 w-4 cursor-pointer text-gray-400 transition"
                                x-bind:class="collapsed ? '-rotate-90' : ''"
                                x-on:click="collapsed = !collapsed"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Task list --}}
                    <div
                        x-show="!collapsed"
                        class="border-t border-gray-100 dark:border-gray-800"
                    >
                        <x-tl.sortable-container
                            modelType="task"
                            :endpoint="route('reorder')"
                            :group="'tasks'"
                            :containerId="'group-tasks-' . $group->id"
                            :moveEndpoint="route('tasks.move')"
                            :groupId="$group->id"
                        >
                            @foreach($group->tasks->sortBy('sort_order') as $task)
                                <x-tl.task-card :task="$task" :hideWhenDone="true" />
                            @endforeach
                        </x-tl.sortable-container>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Ungrouped tasks --}}
        @if($hasUngrouped)
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                    <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Ungrouped</span>
                </div>
                <x-tl.sortable-container
                    modelType="task"
                    :endpoint="route('reorder')"
                    :group="'tasks'"
                    containerId="ungrouped-tasks"
                    :moveEndpoint="route('tasks.move')"
                    :groupId="0"
                >
                    @foreach($ungroupedTasks->sortBy('sort_order') as $task)
                        <x-tl.task-card :task="$task" :hideWhenDone="true" />
                    @endforeach
                </x-tl.sortable-container>
            </div>
        @endif
    </div>
@endif
