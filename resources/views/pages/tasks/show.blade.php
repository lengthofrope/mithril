@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    @php
        $taskEndpoint = '/api/v1/tasks/' . $task->id;
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Main content --}}
        <div class="xl:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                {{-- Title --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="title"
                        :value="$task->title"
                        type="text"
                        label="Title"
                    />
                </div>

                {{-- Row: Priority + Status + Deadline --}}
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="priority"
                        :value="$task->priority instanceof \BackedEnum ? $task->priority->value : (string) $task->priority"
                        type="select"
                        label="Priority"
                        :options="$priorityOptions"
                    />

                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="status"
                        :value="$task->status instanceof \BackedEnum ? $task->status->value : (string) $task->status"
                        type="select"
                        label="Status"
                        :options="$statusOptions"
                    />

                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="deadline"
                        :value="$task->deadline ? $task->deadline->format('Y-m-d') : ''"
                        type="date"
                        label="Deadline"
                    />
                </div>

                {{-- Row: Team + Member (linked filtering) --}}
                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2"
                    x-data="{
                        allMembers: @js($memberOptions),
                        selectedTeamId: @js((string) ($task->team_id ?? '')),
                        get filteredMemberOptions() {
                            return this.selectedTeamId
                                ? this.allMembers.filter(m => String(m.team_id) === String(this.selectedTeamId))
                                : this.allMembers;
                        },
                    }"
                >
                    {{-- Team select --}}
                    <div
                        x-data="autoSaveField({ endpoint: @js($taskEndpoint), field: 'team_id' })"
                        x-init="value = @js((string) ($task->team_id ?? ''))"
                        x-effect="selectedTeamId = value"
                        class="flex flex-col gap-1.5"
                    >
                        <label for="asf-team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select
                            id="asf-team_id"
                            name="team_id"
                            x-model="value"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            @foreach(array_merge([['value' => '', 'label' => '— None —']], $teamOptions) as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <x-tl.auto-save-status />
                    </div>

                    {{-- Member select (filtered by team) --}}
                    <div
                        x-data="autoSaveField({ endpoint: @js($taskEndpoint), field: 'team_member_id' })"
                        x-init="$nextTick(() => { value = @js((string) ($task->team_member_id ?? '')); })"
                        class="flex flex-col gap-1.5"
                    >
                        <label for="asf-team_member_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assigned to</label>
                        <select
                            id="asf-team_member_id"
                            name="team_member_id"
                            x-model="value"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">— None —</option>
                            <template x-for="opt in filteredMemberOptions" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <x-tl.auto-save-status />
                    </div>
                </div>

                {{-- Row: Category + Group + Private --}}
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="task_category_id"
                        :value="(string) ($task->task_category_id ?? '')"
                        type="select"
                        label="Category"
                        :options="array_merge([['value' => '', 'label' => '— None —']], $categoryOptions)"
                    />

                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="task_group_id"
                        :value="(string) ($task->task_group_id ?? '')"
                        type="select"
                        label="Group"
                        :options="array_merge([['value' => '', 'label' => '— None —']], $groupOptions)"
                    />

                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="is_private"
                        :value="$task->is_private ? '1' : '0'"
                        type="select"
                        label="Private"
                        :options="[['value' => '0', 'label' => 'No'], ['value' => '1', 'label' => 'Yes']]"
                    />
                </div>

                {{-- Row: Recurrence (Recurring + Interval + Custom days) --}}
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <x-tl.recurrence-settings :task="$task" />
                </div>

                {{-- Description --}}
                <div>
                    <x-tl.auto-save-field
                        :endpoint="$taskEndpoint"
                        field="description"
                        :value="$task->description ?? ''"
                        type="textarea"
                        label="Description"
                    />
                </div>
            </div>

            {{-- Linked follow-ups --}}
            @if($task->followUps->isNotEmpty())
                <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Linked follow-ups</h3>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($task->followUps as $followUp)
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <a
                                        href="{{ route('follow-ups.show', $followUp) }}"
                                        class="text-sm text-gray-800 hover:text-blue-600 dark:text-white/90 dark:hover:text-blue-400"
                                    >
                                        {{ $followUp->description }}
                                    </a>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $followUp->status === \App\Enums\FollowUpStatus::Done
                                        ? 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500'
                                        : 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400' }}">
                                    {{ ucfirst($followUp->status->value) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Actions --}}
            <div class="mt-4 flex items-center gap-3">
                <a
                    href="{{ route('tasks.index') }}"
                    class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                >
                    &larr; Back to tasks
                </a>

                <div class="ml-auto flex items-center gap-2">
                    <div x-data="{ isProcessing: false }" class="inline">
                        <button
                            type="button"
                            x-bind:disabled="isProcessing"
                            x-on:click="
                                if (isProcessing) return;
                                isProcessing = true;
                                try {
                                    const response = await fetch('{{ route('tasks.create-follow-up', $task) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                        },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({}),
                                    });
                                    const json = await response.json();
                                    if (json.success && json.data?.follow_up_url) {
                                        window.location.href = json.data.follow_up_url;
                                    }
                                } finally {
                                    isProcessing = false;
                                }
                            "
                            class="flex items-center gap-1 rounded-lg border border-orange-300 bg-orange-50 px-3 py-1.5 text-xs font-medium text-orange-700 transition hover:bg-orange-100 disabled:opacity-50 dark:border-orange-700/50 dark:bg-orange-500/10 dark:text-orange-400 dark:hover:bg-orange-500/20"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Create follow-up
                        </button>
                    </div>

                    <div x-data="{ isProcessing: false }" class="inline">
                        <button
                            type="button"
                            x-bind:disabled="isProcessing"
                            x-on:click="
                                if (isProcessing) return;
                                if (!confirm('This will mark the task as done and create a follow-up. Continue?')) return;
                                isProcessing = true;
                                try {
                                    const response = await fetch('{{ route('tasks.convert-to-follow-up', $task) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                        },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({}),
                                    });
                                    const json = await response.json();
                                    if (json.success && json.data?.follow_up_url) {
                                        window.location.href = json.data.follow_up_url;
                                    }
                                } finally {
                                    isProcessing = false;
                                }
                            "
                            class="flex items-center gap-1 rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:border-blue-700/50 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                            </svg>
                            Convert to follow-up
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Activity feed sidebar --}}
        <div class="xl:col-span-1">
            <x-tl.activity-feed
                :parent="$task"
                parentType="tasks"
                :activities="$task->getActivityFeed()"
            />
        </div>
    </div>
@endsection
