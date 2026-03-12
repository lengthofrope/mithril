<div
    x-data="emailActions(email.id, email.links ?? [], email.sender_is_team_member ?? false)"
    class="rounded-xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800"
>
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
                <template x-for="cat in (email.categories || [])" :key="cat">
                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[0.625rem] font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400" x-text="cat"></span>
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

            {{-- Actions dropdown (create resource, linked items) --}}
            <x-tl.email-actions />

            {{-- Dismiss --}}
            <button @click="$dispatch('dismiss-email', { emailId: email.id })"
                class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700 dark:hover:text-red-400"
                title="Dismiss" aria-label="Dismiss email">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</div>
