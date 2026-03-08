<div class="flex h-4 items-center" aria-live="polite" aria-atomic="true">
    <span
        x-show="status === 'saving'"
        x-cloak
        class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500"
    >
        <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        Saving…
    </span>
    <span
        x-show="status === 'saved'"
        x-cloak
        class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
    >
        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Saved
    </span>
    <span
        x-show="status === 'error'"
        x-cloak
        class="flex items-center gap-1 text-xs text-red-600 dark:text-red-400"
    >
        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Failed to save
    </span>
</div>
