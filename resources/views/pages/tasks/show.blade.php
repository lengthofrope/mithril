@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $task->title }}" />

    @php
        $taskEndpoint = '/api/v1/tasks/' . $task->id;
    @endphp

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

        {{-- Row: Priority + Status --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        </div>

        {{-- Row: Team + Member --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-tl.auto-save-field
                :endpoint="$taskEndpoint"
                field="team_id"
                :value="(string) ($task->team_id ?? '')"
                type="select"
                label="Team"
                :options="array_merge([['value' => '', 'label' => '— None —']], $teamOptions)"
            />

            <x-tl.auto-save-field
                :endpoint="$taskEndpoint"
                field="team_member_id"
                :value="(string) ($task->team_member_id ?? '')"
                type="select"
                label="Assigned to"
                :options="array_merge([['value' => '', 'label' => '— None —']], $memberOptions)"
            />
        </div>

        {{-- Row: Category + Group --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        </div>

        {{-- Row: Deadline + Private --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-tl.auto-save-field
                :endpoint="$taskEndpoint"
                field="deadline"
                :value="$task->deadline ? $task->deadline->format('Y-m-d') : ''"
                type="date"
                label="Deadline"
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

    <div class="mt-4">
        <a
            href="{{ route('tasks.index') }}"
            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            &larr; Back to tasks
        </a>
    </div>
@endsection
