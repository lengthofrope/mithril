{{-- Add task modal --}}
<div
    x-data="{
        addOpen: false,
        selectedTeamId: '',
        allMembers: @js($memberOptions),
        get filteredMembers() {
            if (!this.selectedTeamId) return this.allMembers;
            return this.allMembers.filter(m => String(m.team_id) === String(this.selectedTeamId));
        },
    }"
>
    <button
        type="button"
        x-on:click="addOpen = !addOpen"
        class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New task
    </button>

    <div
        x-show="addOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        x-on:keydown.escape.window="addOpen = false"
    >
        <div
            class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            x-on:click.outside="addOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Create a new task</h2>
            <form method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="new-task-title" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <input
                        id="new-task-title"
                        type="text"
                        name="title"
                        placeholder="Task title…"
                        required
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                    >
                </div>

                <div class="mb-3 grid grid-cols-2 gap-3">
                    <div>
                        <label for="new-task-priority" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Priority</label>
                        <select
                            id="new-task-priority"
                            name="priority"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    <div>
                        <label for="new-task-category" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <select
                            id="new-task-category"
                            name="task_category_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">No category</option>
                            @foreach($categoryOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 grid grid-cols-2 gap-3">
                    <div>
                        <label for="new-task-group" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Group</label>
                        <select
                            id="new-task-group"
                            name="task_group_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">No group</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-data="datePicker()">
                        <label for="new-task-deadline" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Deadline</label>
                        <input
                            id="new-task-deadline"
                            type="text"
                            name="deadline"
                            x-ref="input"
                            placeholder="YYYY-MM-DD"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-3">
                    <div>
                        <label for="new-task-team" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select
                            id="new-task-team"
                            name="team_id"
                            x-model="selectedTeamId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">No team</option>
                            @foreach($teamOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="new-task-member" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Assigned to</label>
                        <select
                            id="new-task-member"
                            name="team_member_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">No assignee</option>
                            <template x-for="member in filteredMembers" :key="member.value">
                                <option :value="member.value" x-text="member.label"></option>
                            </template>
                        </select>
                    </div>
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
