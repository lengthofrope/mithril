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
        <div x-data="emailPage" class="space-y-4">
            {{-- Source filter tabs --}}
            <div class="flex gap-2">
                <template x-for="source in ['all', 'flagged', 'categorized', 'unread']" :key="source">
                    <button
                        @click="setFilter(source)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium capitalize transition"
                        :class="sourceFilter === source
                            ? 'bg-brand-500 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'"
                        x-text="source"
                    ></button>
                </template>
            </div>

            {{-- Loading state --}}
            <div x-show="isLoading" class="py-8 text-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">Loading emails...</p>
            </div>

            {{-- Error state --}}
            <div x-show="errorMessage" x-cloak class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                <p x-text="errorMessage"></p>
            </div>

            {{-- Empty state --}}
            <div x-show="!isLoading && !errorMessage && emails.length === 0" x-cloak
                class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">No emails found.</p>
            </div>

            {{-- Grouped by category view --}}
            <template x-if="showCategoryGroups && !isLoading && emails.length > 0">
                <div class="space-y-6">
                    <template x-for="group in categoryGroups" :key="group.name">
                        <div>
                            <h2 class="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="group.name"></h2>
                            <div class="space-y-2">
                                <template x-for="email in group.emails" :key="email.id">
                                    @include('pages.mail._email-card')
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Flat list view --}}
            <template x-if="!showCategoryGroups && !isLoading && emails.length > 0">
                <div class="space-y-2">
                    <template x-for="email in emails" :key="email.id">
                        @include('pages.mail._email-card')
                    </template>
                </div>
            </template>
        </div>
    @endif
@endsection
