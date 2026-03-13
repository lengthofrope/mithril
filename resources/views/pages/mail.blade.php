@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="E-mail" />

    @if (!$isMicrosoftConnected)
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Connect your Microsoft account in Settings to sync your emails.
            </p>
            <a href="{{ route('settings.index') }}"
                class="mt-3 inline-block text-sm font-medium text-brand-500 hover:underline">
                Go to Settings
            </a>
        </div>
    @else
        <div x-data="emailPage">
            {{-- Card wrapper matching calendar style --}}
            <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                {{-- Header with filter tabs and count --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">E-mail</h2>
                        <div class="flex gap-1">
                            <template x-for="source in ['all', 'flagged', 'categorized', 'unread']" :key="source">
                                <button
                                    @click="setFilter(source)"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium capitalize transition"
                                    :class="sourceFilter === source
                                        ? 'bg-brand-500 text-white'
                                        : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'"
                                    x-text="source"
                                ></button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-tl.sync-button endpoint="/api/v1/sync/emails" />
                        <span
                            x-show="!isLoading && emails.length > 0"
                            x-text="emails.length"
                            class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-600 dark:bg-teal-500/15 dark:text-teal-400"
                        ></span>
                    </div>
                </div>

                {{-- Loading state --}}
                <div x-show="isLoading" class="px-5 py-8 text-center">
                    <svg class="mx-auto h-5 w-5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Loading emails...</p>
                </div>

                {{-- Error state --}}
                <div x-show="errorMessage" x-cloak @click="errorMessage = ''" class="m-4 cursor-pointer rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" role="alert">
                    <p x-text="errorMessage"></p>
                </div>

                {{-- Empty state --}}
                <div x-show="!isLoading && !errorMessage && emails.length === 0" x-cloak class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-400 dark:text-gray-500">No emails found.</p>
                </div>

                {{-- Grouped by category view --}}
                <template x-if="showCategoryGroups && !isLoading && emails.length > 0">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="group in categoryGroups" :key="group.name">
                            <div x-data="{ open: true }">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between bg-gray-50 px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400"
                                    @click="open = !open"
                                    :aria-expanded="open.toString()"
                                >
                                    <span>
                                        <span x-text="group.name"></span>
                                        <span class="ml-1 font-normal" x-text="'(' + group.emails.length + ')'"></span>
                                    </span>
                                    <svg
                                        class="h-3.5 w-3.5 shrink-0 transition-transform"
                                        :class="{ 'rotate-180': open }"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                    >
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>
                                <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <template x-for="email in group.emails" :key="email.id">
                                        @include('pages.mail._email-card')
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Date-grouped view (default) --}}
                <template x-if="!showCategoryGroups && !isLoading && emails.length > 0">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="group in dateGroups" :key="group.label">
                            <div x-data="{ open: group.defaultOpen }">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between bg-gray-50 px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400"
                                    @click="open = !open"
                                    :aria-expanded="open.toString()"
                                >
                                    <span>
                                        <span x-text="group.label"></span>
                                        <span class="ml-1 font-normal" x-text="'(' + group.emails.length + ')'"></span>
                                    </span>
                                    <svg
                                        class="h-3.5 w-3.5 shrink-0 transition-transform"
                                        :class="{ 'rotate-180': open }"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                    >
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>
                                <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <template x-for="email in group.emails" :key="email.id">
                                        @include('pages.mail._email-card')
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </section>
        </div>
    @endif
@endsection
