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
                title="Overdue tasks"
                :count="$counters['overdue_tasks']"
                color="red"
                :link="route('tasks.index')"
                counterKey="overdue_tasks"
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
        <div
            x-data="refreshable({ url: '{{ route('partials.dashboard.tasks') }}', topics: ['tasks'], pollInterval: 30000 })"
        >
            <div data-refresh-target>
                @include('partials.dashboard.tasks', [
                    'todayTasks' => $todayTasks,
                    'upcomingTasks' => $upcomingTasks,
                ])
            </div>
        </div>

        {{-- Follow-ups + Bilas (app-specific) --}}
        <div class="flex flex-col gap-6">
            <div
                x-data="refreshable({ url: '{{ route('partials.dashboard.follow-ups') }}', topics: ['follow_ups'], pollInterval: 30000 })"
            >
                <div data-refresh-target>
                    @include('partials.dashboard.follow-ups', [
                        'todayFollowUps' => $todayFollowUps,
                        'upcomingFollowUps' => $upcomingFollowUps,
                    ])
                </div>
            </div>

            <div
                x-data="refreshable({ url: '{{ route('partials.dashboard.bilas') }}', topics: ['bilas'], pollInterval: 30000 })"
            >
                <div data-refresh-target>
                    @include('partials.dashboard.bilas', [
                        'todayBilas' => $todayBilas,
                        'upcomingBilas' => $upcomingBilas,
                    ])
                </div>
            </div>
        </div>

        {{-- Upcoming calendar + Flagged emails (Office 365) + Jira --}}
        <div class="flex flex-col gap-6">
            @if($calendarEvents !== null)
                <div
                    x-data="refreshable({ url: '{{ route('partials.dashboard.calendar') }}', topics: ['calendar'], pollInterval: 60000 })"
                >
                    <div data-refresh-target>
                        @include('partials.dashboard.calendar', [
                            'calendarEvents' => $calendarEvents,
                            'userTimezone' => $userTimezone,
                            'isMicrosoftConnected' => $isMicrosoftConnected,
                        ])
                    </div>
                </div>
            @endif

            @if($flaggedEmails !== null)
                <div
                    x-data="refreshable({ url: '{{ route('partials.dashboard.emails') }}', topics: ['emails'], pollInterval: 30000 })"
                >
                    <div data-refresh-target>
                        @include('partials.dashboard.emails', [
                            'flaggedEmails' => $flaggedEmails,
                            'isMicrosoftConnected' => $isMicrosoftConnected,
                        ])
                    </div>
                </div>
            @endif

            @if($isJiraConnected)
                <x-tl.jira-widget class="flex-1" />
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
