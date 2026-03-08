@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    {{-- Greeting --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $greeting }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ now()->format('l, d F Y') }}
            </p>
        </div>
    </div>

    {{-- Counter cards --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-tl.counter-card
            title="Open tasks"
            :count="$counters['open_tasks']"
            color="blue"
            :link="route('tasks.index')"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </x-slot>
        </x-tl.counter-card>

        <x-tl.counter-card
            title="Urgent tasks"
            :count="$counters['urgent_tasks']"
            color="red"
            :link="route('tasks.index', ['priority' => 'urgent'])"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </x-slot>
        </x-tl.counter-card>

        <x-tl.counter-card
            title="Overdue follow-ups"
            :count="$counters['overdue_follow_ups']"
            color="orange"
            :link="route('follow-ups.index')"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </x-slot>
        </x-tl.counter-card>

        <x-tl.counter-card
            title="Bilas this week"
            :count="$counters['bilas_this_week']"
            color="purple"
            :link="route('bilas.index')"
        >
            <x-slot name="icon">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </x-slot>
        </x-tl.counter-card>
    </div>

    {{-- Quick-add task form --}}
    <div class="mb-8 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Quick-add task</h2>
        <form
            method="POST"
            action="{{ route('tasks.store') }}"
            class="flex flex-wrap items-end gap-3"
        >
            @csrf
            <div class="flex-1 min-w-48">
                <label for="quick-task-title" class="sr-only">Task title</label>
                <input
                    id="quick-task-title"
                    type="text"
                    name="title"
                    placeholder="New task title…"
                    required
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                >
            </div>

            <div>
                <label for="quick-task-priority" class="sr-only">Priority</label>
                <select
                    id="quick-task-priority"
                    name="priority"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                >
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <button
                type="submit"
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add task
            </button>
        </form>
    </div>

    {{-- Today section --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Tasks due today --}}
        <div class="xl:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        Tasks due today
                    </h2>
                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600 dark:bg-blue-500/15 dark:text-blue-400">
                        {{ $todayTasks->count() }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($todayTasks as $task)
                        <div class="px-5 py-3">
                            <x-tl.task-card :task="$task" />
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            No tasks due today.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Follow-ups needing attention --}}
        <div class="xl:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        Follow-ups needing attention
                    </h2>
                    <span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-600 dark:bg-orange-500/15 dark:text-orange-400">
                        {{ $todayFollowUps->count() }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($todayFollowUps as $followUp)
                        <div class="px-5 py-3">
                            <x-tl.follow-up-card :followUp="$followUp" />
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            No follow-ups today.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Bilas today --}}
        <div class="xl:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        Bilas today
                    </h2>
                    <span class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-600 dark:bg-purple-500/15 dark:text-purple-400">
                        {{ $todayBilas->count() }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($todayBilas as $bila)
                        <a
                            href="{{ route('bilas.show', $bila->id) }}"
                            class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            @if(isset($bila->member) && $bila->member)
                                <x-tl.team-member-avatar :member="$bila->member" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $bila->member->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($bila->scheduled_date)->format('H:i') }}
                                    </p>
                                </div>
                            @else
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                        Bila #{{ $bila->id }}
                                    </p>
                                </div>
                            @endif
                            <svg class="h-4 w-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 18l6-6-6-6"/>
                            </svg>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            No bilas scheduled today.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics widgets --}}
    @if(isset($dashboardWidgets) && $dashboardWidgets->isNotEmpty())
        <div class="mt-8" x-data="analyticsBoard({ context: 'dashboard', reorderEndpoint: '{{ route('reorder') }}', widgetEndpoint: '{{ route('analytics.widgets.store') }}' })">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Analytics</h2>
            <div
                x-ref="widgetGrid"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                @foreach($dashboardWidgets as $widget)
                    <x-tl.analytics-widget :widget="$widget" context="dashboard" />
                @endforeach
            </div>

            <div
                x-show="hasReorderError"
                x-cloak
                class="mt-2 text-xs text-red-600 dark:text-red-400"
                aria-live="assertive"
            >
                Failed to save new order. Please try again.
            </div>
        </div>
    @endif
@endsection
