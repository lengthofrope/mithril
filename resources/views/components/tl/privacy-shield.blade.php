@props(['isPrivate' => false])

<div x-data="privacyToggle({ isPrivate: @js($isPrivate) })">
    <template x-if="isPrivate">
        <button
            type="button"
            x-on:click="toggle()"
            class="flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500 transition hover:border-gray-400 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-300"
            aria-label="Reveal private content"
        >
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <span>Private — click to reveal</span>
        </button>
    </template>

    <template x-if="!isPrivate">
        <div>
            {{ $slot }}
        </div>
    </template>
</div>
