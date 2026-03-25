@props([
    'endpoint',
    'resultsSelector',
    'filters' => [],
])

@php
    $searchFilters = collect($filters)->where('type', 'search');
    $selectFilters = collect($filters)->whereIn('type', ['select', 'multi-select', 'date-range']);
    $booleanFilters = collect($filters)->where('type', 'boolean');
    $hasDropdowns = $selectFilters->isNotEmpty() || $booleanFilters->isNotEmpty();
    $selectClasses = 'w-full rounded-lg border border-gray-200 bg-white pl-3 py-1.5 text-sm text-gray-800 sm:w-40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-400';
@endphp

<div
    x-data="{
        ...filterManager({
            endpoint: '{{ $endpoint }}',
            resultsSelector: '{{ $resultsSelector }}',
            filters: @js($filters),
        }),
        filtersOpen: false,
    }"
    class="space-y-3"
>
    {{-- Primary row: search + filter toggle + reset --}}
    <div class="flex items-center gap-3">
        @foreach($searchFilters as $filter)
            <div class="relative flex-1 max-w-sm">
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
                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-400"
                >
            </div>
        @endforeach

        @if($hasDropdowns)
            <button
                type="button"
                x-on:click="filtersOpen = !filtersOpen"
                :class="filtersOpen ? 'border-brand-400 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400'"
                class="flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm transition hover:border-brand-300 hover:text-brand-600 dark:bg-gray-900 dark:hover:border-brand-500 dark:hover:text-brand-400"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/>
                    <circle cx="6" cy="12" r="1.5"/><circle cx="10" cy="18" r="1.5"/>
                </svg>
                <span>Filters</span>
                <span
                    x-show="activeFilterCount > 0"
                    x-text="activeFilterCount"
                    x-cloak
                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-500 px-1.5 text-xs font-medium text-white"
                ></span>
            </button>
        @endif

        {{-- Loading spinner --}}
        <div
            x-show="isLoading"
            x-cloak
            class="flex items-center text-gray-400"
            aria-live="polite"
        >
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>

        <button
            type="button"
            x-show="activeFilterCount > 0"
            x-cloak
            x-on:click="resetFilters()"
            class="text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            Clear all
        </button>
    </div>

    {{-- Collapsible filter panel --}}
    @if($hasDropdowns)
        <div
            x-show="filtersOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            @if($selectFilters->isNotEmpty())
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:flex sm:flex-wrap sm:items-end">
                    @foreach($selectFilters as $filter)
                        <div class="flex min-w-0 flex-col gap-1">
                            <label
                                for="filter-{{ $filter['field'] }}"
                                class="text-xs font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $filter['label'] }}
                            </label>

                            @if($filter['type'] === 'date-range')
                                <div x-data="datePicker()">
                                    <input
                                        id="filter-{{ $filter['field'] }}"
                                        type="text"
                                        x-ref="input"
                                        x-model="filterState['{{ $filter['field'] }}']"
                                        placeholder="YYYY-MM-DD"
                                        class="w-full rounded-lg border border-gray-200 bg-white pl-3 pr-3 py-1.5 text-sm text-gray-800 sm:w-40 placeholder:text-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-400"
                                    >
                                </div>
                            @elseif(!empty($filter['linked_to']))
                                <select
                                    id="filter-{{ $filter['field'] }}"
                                    x-model="filterState['{{ $filter['field'] }}']"
                                    class="{{ $selectClasses }}"
                                >
                                    <option value="">All</option>
                                    <template x-for="opt in @js($filter['options'] ?? []).filter(o => !filterState['{{ $filter['linked_to'] }}'] || String(o.{{ $filter['linked_to'] }}) === String(filterState['{{ $filter['linked_to'] }}']))" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            @elseif($filter['type'] === 'multi-select')
                                <select
                                    id="filter-{{ $filter['field'] }}"
                                    x-model="filterState['{{ $filter['field'] }}']"
                                    multiple
                                    class="w-full rounded-lg border border-gray-200 bg-white pl-3 pr-3 py-1.5 text-sm text-gray-800 sm:w-40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-400"
                                >
                                    @foreach($filter['options'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <select
                                    id="filter-{{ $filter['field'] }}"
                                    x-model="filterState['{{ $filter['field'] }}']"
                                    class="{{ $selectClasses }}"
                                >
                                    <option value="">All</option>
                                    @foreach($filter['options'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($booleanFilters->isNotEmpty())
                <div class="{{ $selectFilters->isNotEmpty() ? 'mt-3 border-t border-gray-100 pt-3 dark:border-gray-800' : '' }} flex flex-wrap gap-2">
                    @foreach($booleanFilters as $filter)
                        <label
                            for="filter-{{ $filter['field'] }}"
                            class="group flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="filterState['{{ $filter['field'] }}']
                                ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'"
                        >
                            <input
                                id="filter-{{ $filter['field'] }}"
                                type="checkbox"
                                x-model="filterState['{{ $filter['field'] }}']"
                                class="sr-only"
                            >
                            <svg
                                x-show="filterState['{{ $filter['field'] }}']"
                                x-cloak
                                class="h-3 w-3"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            {{ $filter['label'] }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div
        x-show="hasError"
        x-cloak
        class="text-xs text-red-600 dark:text-red-400"
        aria-live="assertive"
    >
        Failed to load results. Please try again.
    </div>
</div>
