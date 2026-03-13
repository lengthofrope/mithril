@props([
    'limit' => 5,
])

<div
    {{ $attributes->class(['rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]']) }}
    x-data="{
        issues: [],
        total: 0,
        isLoading: true,
        errorMessage: '',
        limit: {{ $limit }},

        async init() {
            await this.fetchIssues();
        },

        async fetchIssues() {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const response = await fetch(`/api/v1/jira-issues/dashboard?limit=${this.limit}`, {
                    headers: { 'Accept': 'application/json' },
                });

                const json = await response.json();

                if (json.success && json.data) {
                    this.issues = json.data.issues;
                    this.total = json.data.total;
                } else {
                    this.errorMessage = json.message ?? 'Failed to load Jira issues.';
                }
            } catch {
                this.errorMessage = 'Failed to load Jira issues.';
            } finally {
                this.isLoading = false;
            }
        }
    }"
>
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Jira Issues</h2>
        <div class="flex items-center gap-2">
            <x-tl.sync-button endpoint="/api/v1/sync/jira" />
            <span
                x-show="!isLoading && total > 0"
                x-text="total"
                class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-600 dark:bg-teal-500/15 dark:text-teal-400"
            ></span>
            <a href="{{ route('jira.index') }}" class="text-xs text-brand-500 hover:underline">View all</a>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="isLoading" class="px-5 py-6 text-center">
        <svg class="mx-auto h-5 w-5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
    </div>

    {{-- Error --}}
    <div x-show="errorMessage" x-cloak class="m-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" role="alert">
        <p x-text="errorMessage"></p>
    </div>

    {{-- Empty --}}
    <div x-show="!isLoading && !errorMessage && issues.length === 0" x-cloak class="px-5 py-6 text-center">
        <p class="text-sm text-gray-400 dark:text-gray-500">No assigned Jira issues.</p>
    </div>

    {{-- Issue list --}}
    <div x-show="!isLoading && issues.length > 0" x-cloak class="divide-y divide-gray-100 dark:divide-gray-800">
        <template x-for="issue in issues" :key="issue.id">
            <a
                :href="issue.web_url"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="shrink-0 text-xs font-mono font-medium text-gray-500 dark:text-gray-400" x-text="issue.issue_key"></span>
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="issue.summary"></p>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                        <span x-text="issue.status_name"></span>
                        <template x-if="issue.priority_name">
                            <span>&middot; <span x-text="issue.priority_name"></span></span>
                        </template>
                    </div>
                </div>
                <svg class="h-4 w-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
            </a>
        </template>
    </div>
</div>
