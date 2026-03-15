@props(['activity'])

@php
    $typeValue = $activity->type instanceof \BackedEnum ? $activity->type->value : (string) $activity->type;
    $isSystem = $typeValue === 'system';
@endphp

<div class="group relative flex gap-3">
    {{-- Type indicator dot --}}
    <div class="mt-1 shrink-0">
        @if($typeValue === 'comment')
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/10" aria-hidden="true">
                <svg class="h-3 w-3 text-blue-500 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </span>
        @elseif($typeValue === 'link')
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-500/10" aria-hidden="true">
                <svg class="h-3 w-3 text-purple-500 dark:text-purple-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
            </span>
        @elseif($typeValue === 'attachment')
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-orange-50 dark:bg-orange-500/10" aria-hidden="true">
                <svg class="h-3 w-3 text-orange-500 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                </svg>
            </span>
        @else
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                <svg class="h-3 w-3 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </span>
        @endif
    </div>

    {{-- Content --}}
    <div class="min-w-0 flex-1">
        {{-- Header: user + timestamp --}}
        @if(!$isSystem)
            <div class="mb-1 flex items-center gap-2">
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ $activity->user->name ?? 'Unknown' }}
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500" title="{{ $activity->created_at->toDateTimeString() }}">
                    {{ $activity->created_at->diffForHumans() }}
                </span>
            </div>
        @endif

        {{-- Type-specific body --}}
        @if($typeValue === 'comment')
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-200">
                {!! nl2br(e($activity->body)) !!}
            </div>

        @elseif($typeValue === 'link')
            @php
                $linkUrl = $activity->getUrl();
                $linkTitle = $activity->getLinkTitle();
            @endphp
            <a
                href="{{ $linkUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="block rounded-lg border border-gray-200 bg-white p-3 text-sm transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
            >
                <span class="font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
                    {{ $linkTitle ?: $linkUrl }}
                </span>
                @if($linkTitle && $linkUrl)
                    <span class="mt-0.5 block truncate text-xs text-gray-400 dark:text-gray-500">
                        {{ $linkUrl }}
                    </span>
                @endif
                @if($activity->body)
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                        {{ $activity->body }}
                    </span>
                @endif
            </a>

        @elseif($typeValue === 'attachment')
            <div class="flex flex-col gap-2">
                @foreach($activity->attachments as $attachment)
                    @php
                        $isImage = str_starts_with($attachment->mime_type, 'image/');
                        $downloadUrl = $attachment->downloadUrl();
                        $previewUrl = $isImage ? $attachment->previewUrl() : null;
                    @endphp
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2.5 dark:border-gray-800 dark:bg-white/[0.03]">
                        @if($isImage)
                            <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                <img
                                    src="{{ $previewUrl }}"
                                    alt="{{ $attachment->filename }}"
                                    class="h-10 w-10 rounded object-cover"
                                    loading="lazy"
                                >
                            </a>
                        @else
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                                <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a
                                href="{{ $downloadUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-sm font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-400"
                            >
                                {{ $attachment->filename }}
                            </a>
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ number_format($attachment->size / 1024, 1) }} KB
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

        @else
            {{-- System event --}}
            <p
                class="py-1 text-xs italic text-gray-400 dark:text-gray-500"
                title="{{ $activity->created_at->toDateTimeString() }}"
            >
                {{ $activity->body }}
                <span class="not-italic">· {{ $activity->created_at->diffForHumans() }}</span>
            </p>
        @endif
    </div>

    {{-- Delete button (non-system only) --}}
    @if(!$isSystem)
        <div class="shrink-0 pt-1">
            <button
                type="button"
                x-on:click="$dispatch('confirm-delete-activity', { id: {{ $activity->id }} })"
                class="rounded p-1 text-gray-300 opacity-0 transition hover:bg-red-50 hover:text-red-500 group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                aria-label="Delete activity"
            >
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </button>
        </div>
    @endif
</div>
