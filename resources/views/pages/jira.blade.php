@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Jira" />

    @if (!$isJiraConnected)
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Connect your Jira Cloud account in Settings to sync your issues.
            </p>
            <a href="{{ route('settings.index') }}"
                class="mt-3 inline-block text-sm font-medium text-brand-500 hover:underline">
                Go to Settings
            </a>
        </div>
    @else
        <div x-data="jiraPage({ dismissEndpoint: '/api/v1/jira-issues' })"
            x-on:jira-dismiss.window="dismiss($event.detail.id)"
            x-on:jira-undismiss.window="undismiss($event.detail.id)"
        >
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                {{-- Header with filter tabs and count --}}
                <div class="flex min-w-0 items-center gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="shrink-0 text-sm font-semibold text-gray-800 dark:text-white/90">Jira Issues</h2>

                    @php $activeSource = request('source', 'assigned'); @endphp
                    <div class="flex shrink-0 gap-1">
                        @foreach(['assigned', 'mentioned', 'watched'] as $source)
                            <a href="{{ route('jira.index', array_merge(request()->query(), ['source' => $source])) }}"
                                class="rounded-md px-2.5 py-1 text-xs font-medium capitalize transition {{ $activeSource === $source ? 'bg-brand-500 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ $source }}
                            </a>
                        @endforeach
                    </div>

                    <span class="mx-1 hidden h-4 w-px bg-gray-200 dark:bg-gray-700 sm:inline-block" aria-hidden="true"></span>

                    {{-- Status category filter --}}
                    <div class="flex shrink-0 gap-1">
                        @foreach(['new' => 'Open', 'indeterminate' => 'In Progress', 'done' => 'Done'] as $cat => $catLabel)
                            <a href="{{ route('jira.index', array_merge(request()->query(), ['status_category' => request('status_category') === $cat ? null : $cat])) }}"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition {{ request('status_category') === $cat ? 'bg-brand-500 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ $catLabel }}
                            </a>
                        @endforeach
                    </div>

                    {{-- Project filter --}}
                    @if(count($projectOptions) > 1)
                        <select
                            @change="selectProject($event.target.value)"
                            class="min-w-0 truncate rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            aria-label="Filter by project"
                            title="{{ collect($projectOptions)->firstWhere('value', request('project_key'))['label'] ?? 'All projects' }}"
                        >
                            <option value="">All projects</option>
                            @foreach($projectOptions as $option)
                                <option value="{{ $option['value'] }}" title="{{ $option['label'] }}" @selected(request('project_key') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @endif

                    <span class="ml-auto shrink-0 rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-600 dark:bg-teal-500/15 dark:text-teal-400">
                        {{ $issues->count() }}
                    </span>
                </div>

                {{-- Empty state --}}
                @if($issues->isEmpty())
                    <div class="px-5 py-8 text-center">
                        <p class="text-sm text-gray-400 dark:text-gray-500">No Jira issues found.</p>
                    </div>
                @endif

                {{-- Issues grouped by project --}}
                @foreach($groupedIssues as $projectKey => $projectIssues)
                    <div class="min-w-0">
                        <div class="flex w-full items-center justify-between bg-gray-50 px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                            <span>
                                {{ $projectIssues->first()->project_name }}
                                <span class="ml-1 font-normal">({{ $projectIssues->count() }})</span>
                            </span>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($projectIssues as $issue)
                                @include('pages.jira._issue-card', ['issue' => $issue])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- Show dismissed toggle --}}
            <div class="mt-4 text-center">
                @if(!request()->boolean('show_dismissed'))
                    <a href="{{ route('jira.index', array_merge(request()->query(), ['show_dismissed' => 1])) }}"
                        class="text-xs text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        Show dismissed issues
                    </a>
                @else
                    <a href="{{ route('jira.index', array_diff_key(request()->query(), ['show_dismissed' => ''])) }}"
                        class="text-xs text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        Hide dismissed issues
                    </a>
                @endif
            </div>
        </div>
    @endif
@endsection
