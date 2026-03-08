@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Settings" />

    <div class="space-y-6 max-w-2xl">

        {{-- Theme --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.06]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Appearance</h2>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Theme</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Switch between light and dark mode</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="$store.theme.toggle()"
                        class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        aria-label="Toggle theme"
                    >
                        <span x-show="$store.theme.theme === 'light'">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                        </span>
                        <span x-show="$store.theme.theme === 'dark'" x-cloak>
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </span>
                        <span x-text="$store.theme.theme === 'light' ? 'Light mode' : 'Dark mode'">Light mode</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Push notifications --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.06]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Push notifications</h2>
            </div>
            <div class="p-5">
                <div
                    x-data="{ enabled: @js($pushEnabled ?? false), loading: false }"
                    class="flex items-center justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Enable push notifications</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Receive reminders for follow-ups and scheduled bilas
                        </p>
                    </div>

                    <button
                        type="button"
                        x-on:click="enabled = !enabled"
                        x-bind:aria-checked="enabled"
                        role="switch"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                        x-bind:class="enabled ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'"
                        aria-label="Toggle push notifications"
                    >
                        <span
                            class="inline-block h-4 w-4 rounded-full bg-white shadow transition"
                            x-bind:class="enabled ? 'translate-x-6' : 'translate-x-1'"
                        ></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Task configuration --}}
        <a href="{{ route('settings.tasks') }}" class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.06] dark:hover:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Task configuration</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Manage task categories and groups</p>
            </div>
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        {{-- Data export --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.06]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Data</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Export data</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Download all your data as JSON</p>
                    </div>
                    <a
                        href="{{ route('settings.export') }}"
                        class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export
                    </a>
                </div>

                <div class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Import data</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Restore from a previously exported JSON file</p>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('settings.import') }}"
                        enctype="multipart/form-data"
                        class="flex items-center gap-2"
                    >
                        @csrf
                        <label
                            for="import-file"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Choose file
                        </label>
                        <input
                            id="import-file"
                            type="file"
                            name="import_file"
                            accept=".json"
                            required
                            class="sr-only"
                            x-data
                            x-on:change="$el.closest('form').submit()"
                        >
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection
