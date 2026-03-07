@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $task->title }}" />

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $task->title }}
            </h1>
            <span class="rounded-full px-3 py-1 text-xs font-medium
                @if($task->priority === 'urgent') bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400
                @elseif($task->priority === 'high') bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400
                @elseif($task->priority === 'low') bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400
                @else bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400
                @endif
            ">
                {{ ucfirst($task->priority ?? 'normal') }}
            </span>
        </div>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $task->status ?? 'open')) }}</dd>
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
