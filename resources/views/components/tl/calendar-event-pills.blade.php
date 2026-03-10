@php
    $badgeClasses = [
        'B' => 'bg-purple-100 text-purple-600 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50',
        'T' => 'bg-blue-100 text-blue-600 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50',
        'F' => 'bg-amber-100 text-amber-600 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50',
        'N' => 'bg-green-100 text-green-600 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50',
    ];
    $badgeClassesJson = json_encode($badgeClasses);
@endphp

{{-- Requires a parent element with calendarEventActions x-data --}}

<template x-if="links.length > 0">
    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
        <template x-for="link in links" :key="link.id">
            <a
                :href="link.url"
                class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[0.625rem] font-semibold leading-tight transition-colors"
                :class="({{ $badgeClassesJson }})[link.type] || 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
            >
                <span x-text="link.type"></span>
                <span x-text="link.label"></span>
            </a>
        </template>
    </div>
</template>

<div
    x-show="errorMessage"
    x-transition
    @click="errorMessage = ''"
    class="mt-1 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400"
    role="alert"
>
    <span x-text="errorMessage"></span>
</div>
