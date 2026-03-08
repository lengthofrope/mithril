@props([
    'endpoint',
    'resultsSelector',
    'filters' => [],
])

<div
    x-data="filterManager({
        endpoint: '{{ $endpoint }}',
        resultsSelector: '{{ $resultsSelector }}',
        filters: @js($filters),
    })"
    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"
>
    <div class="flex flex-wrap items-end gap-3">
        @foreach($filters as $filter)
            <div class="flex flex-col gap-1 min-w-0">
                <label
                    for="filter-{{ $filter['field'] }}"
                    class="block text-xs font-medium text-gray-600 dark:text-gray-400"
                >
                    {{ $filter['label'] }}
                </label>

                @if($filter['type'] === 'search')
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                            </svg>
                        </span>
                        <input
                            id="filter-{{ $filter['field'] }}"
                            type="search"
                            x-model.debounce.500ms="filterState['{{ $filter['field'] }}']"
                            placeholder="{{ $filter['label'] }}…"
                            class="w-48 rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>

                @elseif($filter['type'] === 'boolean')
                    <div class="flex items-center gap-2 h-9">
                        <input
                            id="filter-{{ $filter['field'] }}"
                            type="checkbox"
                            x-model="filterState['{{ $filter['field'] }}']"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        >
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $filter['label'] }}</span>
                    </div>

                @elseif($filter['type'] === 'select' && !empty($filter['linked_to']))
                    <select
                        id="filter-{{ $filter['field'] }}"
                        x-model="filterState['{{ $filter['field'] }}']"
                        class="w-40 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="">All</option>
                        <template x-for="opt in @js($filter['options'] ?? []).filter(o => !filterState['{{ $filter['linked_to'] }}'] || String(o.{{ $filter['linked_to'] }}) === String(filterState['{{ $filter['linked_to'] }}']))" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>

                @elseif($filter['type'] === 'select')
                    <select
                        id="filter-{{ $filter['field'] }}"
                        x-model="filterState['{{ $filter['field'] }}']"
                        class="w-40 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="">All</option>
                        @foreach($filter['options'] ?? [] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                @elseif($filter['type'] === 'multi-select')
                    <select
                        id="filter-{{ $filter['field'] }}"
                        x-model="filterState['{{ $filter['field'] }}']"
                        multiple
                        class="w-40 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        @foreach($filter['options'] ?? [] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                @elseif($filter['type'] === 'date-range')
                    <input
                        id="filter-{{ $filter['field'] }}"
                        type="date"
                        x-model="filterState['{{ $filter['field'] }}']"
                        class="w-40 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                @endif
            </div>
        @endforeach

        <div class="ml-auto flex items-center gap-2">
            <div
                x-show="isLoading"
                x-cloak
                class="flex items-center gap-1 text-sm text-gray-400"
                aria-live="polite"
            >
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Loading…
            </div>

            <button
                type="button"
                x-on:click="resetFilters()"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800"
            >
                Reset
            </button>
        </div>
    </div>

    <div
        x-show="hasError"
        x-cloak
        class="mt-3 text-xs text-red-600 dark:text-red-400"
        aria-live="assertive"
    >
        Failed to load results. Please try again.
    </div>
</div>
