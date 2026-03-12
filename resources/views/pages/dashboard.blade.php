@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    {{-- Greeting + Counter cards --}}
    <div class="mb-6 grid grid-cols-1 items-start gap-6 xl:grid-cols-2">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $greeting }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ now()->format('l, d F Y') }}
            </p>
            <div class="elvish-divider mt-3 max-w-xs">
                <span class="elvish-divider-leaf"></span>
            </div>

            {{-- Quick-create buttons --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @include('partials.task-create-modal', [
                    'teamOptions' => $teamOptions,
                    'memberOptions' => $memberOptions,
                    'categoryOptions' => $categoryOptions,
                    'groups' => $groups,
                ])

                @include('partials.follow-up-create-modal', [
                    'teamOptions' => $teamOptions,
                    'memberOptions' => $memberOptions,
                ])

                @include('partials.note-create-modal', [
                    'teamOptions' => $teamOptions,
                    'memberOptions' => $memberOptions,
                ])

                @include('partials.bila-create-modal', [
                    'teamOptions' => $teamOptions,
                    'memberOptions' => $memberOptions,
                ])
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <x-tl.counter-card
                title="Open tasks"
                :count="$counters['open_tasks']"
                color="blue"
                :link="route('tasks.index')"
                counterKey="open_tasks"
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
                counterKey="urgent_tasks"
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
                counterKey="overdue_follow_ups"
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
                counterKey="bilas_this_week"
            >
                <x-slot name="icon">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </x-slot>
            </x-tl.counter-card>
        </div>
    </div>

    {{-- Today section --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Tasks --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                    {{ $upcomingTasks->isNotEmpty() ? 'Upcoming tasks' : 'Tasks due today' }}
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

        {{-- Follow-ups + Bilas (app-specific) --}}
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        {{ $upcomingFollowUps->isNotEmpty() ? 'Upcoming follow-ups' : 'Follow-ups needing attention' }}
                    </h2>
                    <span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-600 dark:bg-orange-500/15 dark:text-orange-400">
                        {{ $todayFollowUps->count() + $upcomingFollowUps->count() }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($todayFollowUps as $followUp)
                        <div class="px-5 py-3">
                            <x-tl.follow-up-card :followUp="$followUp" />
                        </div>
                    @empty
                        @if($upcomingFollowUps->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                No follow-ups today.
                            </p>
                        @else
                            <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                All clear for today.
                            </p>
                        @endif
                    @endforelse

                    @if($upcomingFollowUps->isNotEmpty())
                        <div class="elvish-divider mx-5">
                            <span class="elvish-divider-leaf"></span>
                        </div>

                        @foreach($upcomingFollowUps as $followUp)
                            <div class="px-5 py-3">
                                <x-tl.follow-up-card :followUp="$followUp" />
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                        {{ $upcomingBilas->isNotEmpty() ? 'Upcoming bilas' : 'Bilas today' }}
                    </h2>
                    <span class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-600 dark:bg-purple-500/15 dark:text-purple-400">
                        {{ $todayBilas->count() + $upcomingBilas->count() }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($todayBilas as $bila)
                        <a
                            href="{{ route('bilas.show', $bila->id) }}"
                            class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            @if($bila->teamMember)
                                <x-tl.team-member-avatar :member="$bila->teamMember" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                        {{ $bila->teamMember->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $bila->scheduled_date->format('d M Y') }}
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
                        @if($upcomingBilas->isEmpty())
                            <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                No bilas scheduled today.
                            </p>
                        @else
                            <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                All clear for today.
                            </p>
                        @endif
                    @endforelse

                    @if($upcomingBilas->isNotEmpty())
                        <div class="elvish-divider mx-5">
                            <span class="elvish-divider-leaf"></span>
                        </div>

                        @foreach($upcomingBilas as $bila)
                            <a
                                href="{{ route('bilas.show', $bila->id) }}"
                                class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                            >
                                @if($bila->teamMember)
                                    <x-tl.team-member-avatar :member="$bila->teamMember" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $bila->teamMember->name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $bila->scheduled_date->format('d M Y') }}
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
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Upcoming calendar + Flagged emails (Office 365) + Jira --}}
        <div class="flex flex-col gap-6">
            @if($calendarEvents !== null)
                <x-tl.calendar-upcoming :events="$calendarEvents" :timezone="$userTimezone" />
            @endif

            @if($flaggedEmails !== null)
                <x-tl.email-flagged-widget :emails="$flaggedEmails" />
            @endif

            @if($isJiraConnected)
                <x-tl.jira-widget />
            @endif
        </div>
    </div>

    {{-- Analytics widgets --}}
    @if(isset($dashboardWidgets) && $dashboardWidgets->isNotEmpty())
        <div class="mt-6" x-data="analyticsBoard({ context: 'dashboard', reorderEndpoint: '{{ route('reorder') }}', widgetEndpoint: '{{ route('analytics.widgets.store') }}' })" @delete-widget.window="deleteWidget($event.detail.widgetId)">
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
