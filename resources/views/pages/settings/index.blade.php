@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Settings" />

    @if(session('status'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6 max-w-2xl">

        {{-- Theme --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
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

        {{-- Timezone --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Timezone</h2>
            </div>
            <div class="p-5">
                <div
                    x-data="{
                        timezone: '{{ $user->getEffectiveTimezone() }}',
                        saving: false,
                        saved: false,
                        async save() {
                            this.saving = true;
                            this.saved = false;
                            try {
                                const response = await fetch('{{ route('settings.updateTimezone') }}', {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({ timezone: this.timezone }),
                                });
                                if (response.ok) {
                                    this.saved = true;
                                    setTimeout(() => this.saved = false, 2000);
                                }
                            } finally {
                                this.saving = false;
                            }
                        }
                    }"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">Display timezone</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Used for calendar events and time-based greetings</p>
                        </div>
                        <select
                            x-model="timezone"
                            x-on:change="save()"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            aria-label="Select timezone"
                        >
                            @foreach(timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" @selected($tz === $user->getEffectiveTimezone())>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-h-5 mt-1">
                        <span
                            x-show="saved"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="text-xs text-green-600 dark:text-green-400"
                            x-cloak
                        >Saved</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Task configuration --}}
        <a href="{{ route('settings.tasks') }}" class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Task configuration</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Manage task categories and groups</p>
            </div>
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </a>

        {{-- Data pruning --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Data pruning</h2>
            </div>
            <div
                class="p-5 space-y-4"
                x-data="{
                    days: '{{ $user->prune_after_days ?? '' }}',
                    saving: false,
                    saved: false,
                    error: '',
                    get isConfigured() {
                        return this.days !== '' && parseInt(this.days) >= 30;
                    },
                    async save() {
                        this.saving = true;
                        this.saved = false;
                        this.error = '';
                        try {
                            const response = await fetch('{{ route('settings.updatePruneAfterDays') }}', {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    prune_after_days: this.days === '' ? null : parseInt(this.days),
                                }),
                            });
                            if (response.ok) {
                                this.saved = true;
                                setTimeout(() => this.saved = false, 2000);
                            } else {
                                const data = await response.json();
                                this.error = data.errors?.prune_after_days?.[0] ?? 'Failed to save.';
                            }
                        } finally {
                            this.saving = false;
                        }
                    }
                }"
            >
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Auto-delete completed items after</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Completed tasks and follow-ups older than this will be automatically removed. Analytics history is preserved.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            type="number"
                            x-model="days"
                            x-on:change="save()"
                            min="30"
                            max="365"
                            placeholder="Off"
                            class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            aria-label="Retention period in days"
                        >
                        <span class="text-sm text-gray-500 dark:text-gray-400">days</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 min-h-5">
                    <span
                        x-show="saved"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="text-xs text-green-600 dark:text-green-400"
                        x-cloak
                    >Saved</span>
                    <p
                        x-show="error"
                        x-text="error"
                        class="text-xs text-red-600 dark:text-red-400"
                        x-cloak
                    ></p>
                </div>

                <div
                    x-show="isConfigured"
                    x-transition
                    x-cloak
                    class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800"
                >
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Prune now</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Immediately remove completed items older than <span x-text="days"></span> days</p>
                    </div>
                    <form method="POST" action="{{ route('settings.prune') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900/50 dark:bg-transparent dark:text-red-400 dark:hover:bg-red-900/20"
                            aria-label="Prune completed items now"
                        >
                            Prune now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Microsoft Office 365 --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Microsoft Office 365</h2>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </span>
                        <div>
                            @if(auth()->user()->hasMicrosoftConnection())
                                <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                    Connected as {{ auth()->user()->microsoft_email }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Calendar and team availability sync every 5 minutes.</p>
                            @else
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">Not connected</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Connect your Microsoft account to sync your calendar and team availability.</p>
                            @endif
                        </div>
                    </div>

                    @if(auth()->user()->hasMicrosoftConnection())
                        <form method="POST" action="{{ route('microsoft.disconnect') }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900/50 dark:bg-transparent dark:text-red-400 dark:hover:bg-red-900/20"
                                aria-label="Disconnect Microsoft Office 365 account"
                            >
                                Disconnect
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ route('microsoft.redirect') }}"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                            aria-label="Connect your Microsoft Office 365 account"
                        >
                            Connect Office 365
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Data export --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
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
