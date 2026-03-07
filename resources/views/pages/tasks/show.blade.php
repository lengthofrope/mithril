@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $task->title }}" />

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $task->title }}
            </h1>
            <x-tl.priority-badge :priority="$task->priority" />
        </div>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1"><x-tl.status-badge :status="$task->status" /></dd>
            </div>

            @if($task->teamMember)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Assigned to</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $task->teamMember->name }}</dd>
                </div>
            @endif

            @if($task->taskGroup)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Group</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $task->taskGroup->name }}</dd>
                </div>
            @endif

            @if($task->taskCategory)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Category</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $task->taskCategory->name }}</dd>
                </div>
            @endif

            @if($task->deadline)
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Deadline</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($task->deadline)->format('d M Y') }}</dd>
                </div>
            @endif
        </dl>

        @if($task->description)
            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h2 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Description</h2>
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! nl2br(e($task->description)) !!}
                </div>
            </div>
        @endif
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
