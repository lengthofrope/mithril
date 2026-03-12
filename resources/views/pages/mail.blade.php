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

            {{-- Email list --}}
            <div x-show="!isLoading && emails.length > 0" class="space-y-2">
                <template x-for="email in emails" :key="email.id">
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                {{-- Subject + importance --}}
                                <div class="flex items-center gap-2">
                                    <span x-show="email.importance === 'high'" class="text-red-500" title="High importance" aria-label="High importance">!</span>
                                    <span x-show="email.is_flagged" class="text-amber-500" title="Flagged" aria-label="Flagged">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4 24V1h16l-5 8.5L20 18H6v6z"/></svg>
                                    </span>
                                    <h3 class="truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="email.subject"></h3>
                                </div>

                                {{-- Sender + date --}}
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="email.sender_name || email.sender_email || 'Unknown sender'"></span>
                                    <span class="mx-1">&middot;</span>
                                    <span x-text="new Date(email.received_at).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })"></span>
                                    <template x-if="email.flag_due_date">
                                        <span>
                                            <span class="mx-1">&middot;</span>
                                            <span class="text-amber-600 dark:text-amber-400" x-text="'Due ' + new Date(email.flag_due_date).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' })"></span>
                                        </span>
                                    </template>
                                </p>

                                {{-- Body preview --}}
                                <p x-show="email.body_preview" class="mt-1.5 line-clamp-2 text-xs text-gray-400 dark:text-gray-500" x-text="email.body_preview"></p>

                                {{-- Source badges --}}
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <template x-for="source in email.sources" :key="source">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[0.625rem] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400" x-text="source"></span>
                                    </template>
                                </div>

                                {{-- Linked resource badges --}}
                                <div x-show="email._links.length > 0" class="mt-2 flex flex-wrap gap-1">
                                    <template x-for="link in email._links" :key="link.id">
                                        <a :href="link.url"
                                            class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[0.625rem] font-medium text-brand-700 hover:bg-brand-100 dark:bg-brand-900/30 dark:text-brand-400 dark:hover:bg-brand-900/50">
                                            <span x-text="link.type" class="font-bold"></span>
                                            <span x-text="link.label"></span>
                                            <button @click.prevent="unlinkResource(email.id, link.id)" class="ml-0.5 text-brand-400 hover:text-red-500" title="Unlink" aria-label="Unlink resource">&times;</button>
                                        </a>
                                    </template>
                                </div>
                            </div>

                            {{-- Action buttons --}}
                            <div class="flex shrink-0 items-center gap-1">
                                {{-- Open in Outlook --}}
                                <a x-show="email.web_link" :href="email.web_link" target="_blank" rel="noopener noreferrer"
                                    class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                    title="Open in Outlook" aria-label="Open in Outlook">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>

                                {{-- Create resource dropdown --}}
                                <div class="relative">
                                    <button @click="email._menuOpen = !email._menuOpen"
                                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                        title="Create from email" aria-label="Create from email">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    </button>

                                    <div x-show="email._menuOpen"
                                        @click.outside="email._menuOpen = false"
                                        x-transition
                                        x-cloak
                                        class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                        <button @click="createResource(email.id, 'task')" class="flex w-full items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Task</button>
                                        <button @click="createResource(email.id, 'follow-up')" class="flex w-full items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Follow-up</button>
                                        <button @click="createResource(email.id, 'note')" class="flex w-full items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Note</button>
                                        <button @click="createResource(email.id, 'bila')"
                                            :disabled="!email.sender_is_team_member"
                                            :class="email.sender_is_team_member ? 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' : 'text-gray-300 cursor-not-allowed dark:text-gray-600'"
                                            class="flex w-full items-center px-3 py-2 text-sm">Bila</button>
                                    </div>
                                </div>

                                {{-- Dismiss --}}
                                <button @click="dismissEmail(email.id)"
                                    class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700 dark:hover:text-red-400"
                                    title="Dismiss" aria-label="Dismiss email">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endif
@endsection
