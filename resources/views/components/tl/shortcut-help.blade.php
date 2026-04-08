@props([])

<div
    x-data="shortcutHelp()"
    @keydown.escape.window="close()"
>
    {{-- Floating action button: desktop only --}}
    <button
        type="button"
        @click="toggle()"
        aria-label="Keyboard shortcuts"
        class="hidden xl:flex fixed bottom-6 right-6 z-40 h-11 w-11 items-center justify-center rounded-full bg-brand-500 text-white shadow-lg transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 dark:bg-brand-600 dark:hover:bg-brand-500 dark:focus:ring-offset-gray-900"
    >
        <span class="text-base font-semibold leading-none" aria-hidden="true">?</span>
    </button>

    {{-- Overlay backdrop --}}
    <div
        x-show="isOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @click.self="close()"
    >
        {{-- Dialog panel --}}
        <div
            x-show="isOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
            role="dialog"
            aria-modal="true"
            aria-label="Keyboard shortcuts"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="font-philosopher text-base font-semibold text-gray-900 dark:text-white">Keyboard shortcuts</h2>
                <button
                    type="button"
                    @click="close()"
                    aria-label="Close"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Shortcut groups --}}
            <div class="divide-y divide-gray-100 px-6 py-2 dark:divide-gray-800">
                <template x-for="group in groups" :key="group.label">
                    <div class="py-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500" x-text="group.label"></h3>
                        <ul class="space-y-2">
                            <template x-for="shortcut in group.shortcuts" :key="shortcut.description">
                                <li class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-gray-600 dark:text-gray-400" x-text="shortcut.description"></span>
                                    <span class="flex shrink-0 items-center gap-1">
                                        <template x-for="(key, idx) in shortcut.keys" :key="idx">
                                            <span class="flex items-center gap-1">
                                                <kbd
                                                    class="inline-flex h-6 min-w-6 items-center justify-center rounded border border-gray-300 bg-gray-100 px-1.5 font-mono text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                    x-text="key"
                                                ></kbd>
                                                <span
                                                    x-show="idx < shortcut.keys.length - 1 && shortcut.keys[idx + 1] !== '–' && key !== '–'"
                                                    class="text-xs text-gray-400 dark:text-gray-500"
                                                    aria-hidden="true"
                                                >then</span>
                                            </span>
                                        </template>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>

            {{-- Footer hint --}}
            <div class="border-t border-gray-200 px-6 py-3 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Press <kbd class="inline-flex h-5 min-w-5 items-center justify-center rounded border border-gray-300 bg-gray-100 px-1 font-mono text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">Esc</kbd> or <kbd class="inline-flex h-5 min-w-5 items-center justify-center rounded border border-gray-300 bg-gray-100 px-1 font-mono text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">?</kbd> to close
                </p>
            </div>
        </div>
    </div>
</div>
