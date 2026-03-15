@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    @php
        $usedMb = round($usedBytes / 1024 / 1024, 1);
        $maxMb = round($maxBytes / 1024 / 1024);
        $percentage = $maxBytes > 0 ? min(100, round($usedBytes / $maxBytes * 100, 1)) : 0;
        $parentTypeLabels = [
            'App\\Models\\Task' => 'Task',
            'App\\Models\\FollowUp' => 'Follow-up',
            'App\\Models\\Note' => 'Note',
            'App\\Models\\Bila' => 'Bila',
        ];
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-start">

        {{-- Storage usage --}}
        <div class="space-y-6">

            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Storage usage</h2>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="font-heading text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $usedMb }} MB
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                of {{ $maxMb }} MB used
                            </p>
                        </div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $percentage }}%
                        </p>
                    </div>

                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="Storage usage">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $percentage > 90 ? 'bg-error-500' : ($percentage > 70 ? 'bg-warning-500' : 'bg-brand-500') }}"
                            style="width: {{ $percentage }}%"
                        ></div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $attachments->count() }} {{ $attachments->count() === 1 ? 'file' : 'files' }} stored
                    </p>
                </div>
            </div>

        </div>

        {{-- File list --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Files</h2>
            </div>

            @if($attachments->isEmpty())
                <div class="p-5 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No files uploaded yet.</p>
                </div>
            @else
                <div
                    class="divide-y divide-gray-100 dark:divide-gray-800"
                    x-data="{
                        deleting: null,
                        async deleteAttachment(id) {
                            if (this.deleting) return;
                            this.deleting = id;
                            try {
                                const response = await fetch('/api/v1/attachments/' + id, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept': 'application/json',
                                    },
                                });
                                if (response.ok) {
                                    document.getElementById('attachment-' + id)?.remove();
                                    window.location.reload();
                                }
                            } finally {
                                this.deleting = null;
                            }
                        }
                    }"
                >
                    @foreach($attachments as $attachment)
                        <div id="attachment-{{ $attachment->id }}" class="flex items-center gap-3 px-5 py-3">
                            @if($attachment->isImage())
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-blue-50 dark:bg-blue-500/10" aria-hidden="true">
                                    <svg class="h-4 w-4 text-blue-500 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </span>
                            @elseif($attachment->isPdf())
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-red-50 dark:bg-red-500/10" aria-hidden="true">
                                    <svg class="h-4 w-4 text-red-500 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                </span>
                            @else
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $attachment->filename }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $attachment->humanSize() }}
                                    @if($attachment->activity)
                                        · {{ $parentTypeLabels[$attachment->activity->activityable_type] ?? 'Unknown' }}
                                    @endif
                                    · {{ $attachment->created_at->diffForHumans() }}
                                </p>
                            </div>

                            <button
                                type="button"
                                x-on:click="deleteAttachment({{ $attachment->id }})"
                                x-bind:disabled="deleting === {{ $attachment->id }}"
                                class="shrink-0 rounded p-1.5 text-gray-300 transition hover:bg-red-50 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                aria-label="Delete {{ $attachment->filename }}"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
@endsection
