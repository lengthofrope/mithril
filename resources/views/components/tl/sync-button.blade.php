@props([
    'endpoint',
])

<button
    x-data="syncButton('{{ $endpoint }}')"
    x-on:click="sync"
    :disabled="syncing"
    class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 disabled:pointer-events-none disabled:opacity-50 dark:hover:bg-gray-700 dark:hover:text-gray-300"
    title="Refresh"
    aria-label="Refresh"
>
    <svg
        class="h-3.5 w-3.5"
        :class="{ 'animate-spin': syncing }"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
    </svg>
</button>
