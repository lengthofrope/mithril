@php
    $avatarColors = [
        'bg-blue-500', 'bg-purple-500', 'bg-green-500',
        'bg-orange-500', 'bg-pink-500', 'bg-teal-500',
        'bg-indigo-500', 'bg-rose-500',
    ];
@endphp

<div
    x-data="emailActions(email.id, email.links ?? [], email.sender_is_team_member ?? false)"
    class="flex items-start gap-3 px-5 py-3 transition-colors"
    :class="email.is_read ? 'opacity-60 hover:opacity-100' : ''"
    role="row"
>
    {{-- Sender avatar --}}
    <div class="mt-0.5 shrink-0">
        <template x-if="email.sender_avatar_url">
            <img
                :src="email.sender_avatar_url"
                :alt="email.sender_display_name || email.sender_name || ''"
                class="h-8 w-8 rounded-full object-cover"
            >
        </template>
        <template x-if="!email.sender_avatar_url">
            <span
                class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white"
                :class="[{{ json_encode($avatarColors) }}][email.id % {{ count($avatarColors) }}]"
                x-text="email.sender_initials || '?'"
                :title="email.sender_display_name || email.sender_name || 'Unknown sender'"
                aria-hidden="true"
            ></span>
        </template>
    </div>

    {{-- Content column --}}
    <div class="min-w-0 flex-1">
        {{-- Subject line with indicators --}}
        <div class="flex items-center gap-1.5">
            <span x-show="!email.is_read" class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" title="Unread" aria-label="Unread"></span>
            <span x-show="email.importance === 'high'" class="shrink-0 text-red-500" title="High importance" aria-label="High importance">!</span>
            <span x-show="email.is_flagged" class="shrink-0 text-amber-500" title="Flagged" aria-label="Flagged">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 24V1h16l-5 8.5L20 18H6v6z"/></svg>
            </span>
            <p class="truncate text-sm text-gray-800 dark:text-white/90" :class="email.is_read ? 'font-normal' : 'font-semibold'" x-text="email.subject"></p>
        </div>

        {{-- Sender + date --}}
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            <span x-text="email.sender_display_name || email.sender_name || email.sender_email || 'Unknown sender'"></span>
            <template x-if="email.flag_due_date">
                <span>
                    <span class="mx-1">&middot;</span>
                    <span class="text-amber-600 dark:text-amber-400" x-text="'Due ' + new Date(email.flag_due_date).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' })"></span>
                </span>
            </template>
        </p>

        {{-- Body preview --}}
        <p x-show="email.body_preview" class="mt-0.5 line-clamp-1 text-xs text-gray-400 dark:text-gray-500" x-text="email.body_preview"></p>

        {{-- Badges row: categories only (unread/flagged are shown via visual indicators) --}}
        <template x-if="(email.categories || []).length > 0">
            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                <template x-for="cat in (email.categories || [])" :key="cat">
                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[0.625rem] font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400" x-text="cat"></span>
                </template>
            </div>
        </template>

        {{-- Linked resource pills --}}
        <x-tl.email-pills />
    </div>

    {{-- Time column --}}
    <div class="hidden w-16 shrink-0 text-right text-xs text-gray-400 dark:text-gray-500 sm:block">
        <span x-text="new Date(email.received_at).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })"></span>
        <br>
        <span x-text="new Date(email.received_at).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' })"></span>
    </div>

    {{-- Action buttons --}}
    <div class="flex shrink-0 items-center gap-0.5">
        {{-- Open in Outlook --}}
        <a x-show="email.web_link" :href="email.web_link" target="_blank" rel="noopener noreferrer"
            class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            title="Open in Outlook" aria-label="Open in Outlook">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>

        {{-- Actions dropdown --}}
        <x-tl.email-actions />

        {{-- Dismiss --}}
        <button @click="$dispatch('dismiss-email', { emailId: email.id })"
            class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700 dark:hover:text-red-400"
            title="Dismiss" aria-label="Dismiss email">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    {{-- Flagged/importance indicator dot --}}
    <span
        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
        :class="email.is_flagged ? 'bg-amber-500' : (email.importance === 'high' ? 'bg-red-500' : (email.is_read ? 'bg-gray-300 dark:bg-gray-600' : 'bg-brand-500'))"
        :aria-label="email.is_flagged ? 'Flagged' : (email.importance === 'high' ? 'High importance' : (email.is_read ? 'Read' : 'Unread'))"
        role="img"
    ></span>
</div>
