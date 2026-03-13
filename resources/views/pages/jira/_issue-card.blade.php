<div
    x-data="jiraActions({{ $issue->id }}, @js($issue->jiraIssueLinks?->toArray() ?? []), {{ $issue->assignee_email ? 'true' : 'false' }})"
    class="flex items-start gap-3 px-5 py-3 {{ $issue->is_dismissed ? 'opacity-50' : '' }}"
    role="row"
>
    {{-- Issue type + key --}}
    <div class="mt-0.5 shrink-0">
        <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-[0.625rem] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
            {{ $issue->issue_type }}
        </span>
    </div>

    {{-- Content column --}}
    <div class="min-w-0 flex-1">
        <div class="flex min-w-0 items-center gap-1.5">
            <span class="shrink-0 text-xs font-mono font-medium text-gray-500 dark:text-gray-400">{{ $issue->issue_key }}</span>
            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" title="{{ $issue->summary }}">
                {{ $issue->summary }}
            </p>
        </div>

        {{-- Meta row: status + priority + assignee + updated --}}
        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            {{-- Status badge --}}
            <span class="inline-flex rounded-full px-2 py-0.5 text-[0.625rem] font-medium
                @if($issue->status_category === 'done') bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400
                @elseif($issue->status_category === 'indeterminate') bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400
                @else bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400
                @endif
            ">
                {{ $issue->status_name }}
            </span>

            {{-- Priority --}}
            @if($issue->priority_name)
                <span class="inline-flex items-center gap-0.5
                    @if(in_array($issue->priority_name, ['Highest', 'High'])) text-red-500 dark:text-red-400
                    @elseif($issue->priority_name === 'Medium') text-orange-500 dark:text-orange-400
                    @else text-gray-400 dark:text-gray-500
                    @endif
                ">
                    {{ $issue->priority_name }}
                </span>
            @endif

            {{-- Assignee --}}
            @if($issue->assignee_name)
                <span>{{ $issue->assignee_name }}</span>
            @endif

            {{-- Updated date --}}
            <span>{{ $issue->updated_in_jira_at->diffForHumans() }}</span>
        </div>

        {{-- Source badges --}}
        <div class="mt-1.5 flex flex-wrap items-center gap-1">
            @foreach($issue->sources as $source)
                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[0.625rem] font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                    {{ $source }}
                </span>
            @endforeach

            {{-- Labels --}}
            @foreach($issue->labels ?? [] as $label)
                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[0.625rem] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    {{ $label }}
                </span>
            @endforeach
        </div>

        {{-- Linked resource pills --}}
        <x-tl.jira-pills />
    </div>

    {{-- Action buttons --}}
    <div class="flex shrink-0 items-center gap-0.5">
        {{-- Open in Jira --}}
        <a href="{{ $issue->web_url }}" target="_blank" rel="noopener noreferrer"
            class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            title="Open in Jira" aria-label="Open in Jira">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>

        {{-- Actions dropdown --}}
        <x-tl.jira-actions />

        {{-- Dismiss/Undismiss --}}
        @if($issue->is_dismissed)
            <button
                type="button"
                x-on:click="$dispatch('jira-undismiss', { id: {{ $issue->id }} })"
                class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                title="Restore" aria-label="Restore issue"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            </button>
        @else
            <button
                type="button"
                x-on:click="$dispatch('jira-dismiss', { id: {{ $issue->id }} })"
                class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                title="Dismiss" aria-label="Dismiss issue"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        @endif
    </div>
</div>
