{{-- Requires a parent element with calendarEventActions x-data --}}

<div class="relative shrink-0">
    {{-- Action button --}}
    <button
        type="button"
        class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
        @click.stop="menuOpen = !menuOpen"
        :aria-expanded="menuOpen.toString()"
        aria-label="Actions for this event"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="5" r="1"/>
            <circle cx="12" cy="12" r="1"/>
            <circle cx="12" cy="19" r="1"/>
        </svg>
    </button>

    {{-- Dropdown menu --}}
    <div
        x-show="menuOpen"
        x-transition
        @click.outside="menuOpen = false"
        class="absolute right-0 z-50 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        role="menu"
    >
        {{-- Linked resources (quick access with unlink option) --}}
        <template x-if="links.length > 0">
            <div>
                <p class="px-3 py-1.5 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Linked
                </p>
                <template x-for="link in links" :key="link.id">
                    <div class="flex items-center justify-between px-3 py-1.5">
                        <a
                            :href="link.url"
                            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded bg-gray-100 text-xs font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                x-text="link.type"
                            ></span>
                            <span x-text="link.label"></span>
                        </a>
                        <button
                            type="button"
                            class="text-gray-400 transition-colors hover:text-red-500 dark:hover:text-red-400"
                            @click.stop="unlinkResource(link.id)"
                            :disabled="isLoading"
                            aria-label="Remove link"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </template>
                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
            </div>
        </template>

        <p class="px-3 py-1.5 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
            Create from event
        </p>

        <button
            type="button"
            @click="createResource('bila')"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
            role="menuitem"
            :disabled="isLoading"
        >
            <span class="flex h-5 w-5 items-center justify-center rounded bg-purple-100 text-xs font-bold text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">B</span>
            Bila
        </button>

        <button
            type="button"
            @click="createResource('task')"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
            role="menuitem"
            :disabled="isLoading"
        >
            <span class="flex h-5 w-5 items-center justify-center rounded bg-blue-100 text-xs font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">T</span>
            Task
        </button>

        <button
            type="button"
            @click="createResource('follow-up')"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
            role="menuitem"
            :disabled="isLoading"
        >
            <span class="flex h-5 w-5 items-center justify-center rounded bg-amber-100 text-xs font-bold text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">F</span>
            Follow-up
        </button>

        <button
            type="button"
            @click="createResource('note')"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
            role="menuitem"
            :disabled="isLoading"
        >
            <span class="flex h-5 w-5 items-center justify-center rounded bg-green-100 text-xs font-bold text-green-600 dark:bg-green-900/30 dark:text-green-400">N</span>
            Note
        </button>
    </div>

    {{-- Loading spinner --}}
    <div
        x-show="isLoading"
        class="absolute inset-0 flex items-center justify-center"
        aria-live="polite"
        aria-label="Loading"
    >
        <svg class="h-4 w-4 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
    </div>
</div>
