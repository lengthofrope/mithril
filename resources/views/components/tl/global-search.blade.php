@props([])

<div
    x-data="globalSearch({
        taskUrlPattern: '{{ url('/tasks/:id') }}',
        noteUrlPattern: '{{ url('/notes/:id') }}',
        followUpUrlPattern: '{{ url('/follow-ups/:id') }}',
        meetingUrlPattern: '{{ url('/meetings/:id') }}',
        teamMemberUrlPattern: '{{ url('/teams/member/:id') }}',
    })"
    class="relative flex items-center sm:flex-1 sm:min-w-0 sm:mx-4"
    @click.outside="close(); collapseIfEmpty()"
    @keydown.escape.window="closeAndFocus()"
>
    {{-- Mobile search icon button (visible below sm) --}}
    <button
        x-show="!isExpanded"
        @click="expand()"
        type="button"
        aria-label="Open search"
        class="flex sm:hidden items-center justify-center w-10 h-10 text-gray-500 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
    </button>

    {{-- Search input container --}}
    <div
        class="relative w-full"
        :class="isExpanded ? 'fixed inset-x-0 top-0 z-[100000] bg-white dark:bg-gray-800 p-3 shadow-lg sm:relative sm:inset-auto sm:z-auto sm:bg-transparent sm:dark:bg-transparent sm:p-0 sm:shadow-none' : 'hidden sm:block'"
    >
        <div class="relative">
            {{-- Close button for mobile expanded state --}}
            <button
                x-show="isExpanded"
                @click="isExpanded = false; close()"
                type="button"
                aria-label="Close search"
                class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 sm:hidden"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>

            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400 dark:text-gray-500">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </span>

            <input
                x-ref="searchInput"
                type="search"
                x-model="query"
                @input="onInput()"
                @keydown.arrow-down.prevent="onArrowDown()"
                @keydown.arrow-up.prevent="onArrowUp()"
                @keydown.enter.prevent="onEnter()"
                @blur="collapseIfEmpty()"
                placeholder="Search..."
                role="combobox"
                aria-label="Global search"
                aria-expanded="isOpen"
                aria-haspopup="listbox"
                aria-controls="search-results-listbox"
                :aria-activedescendant="activeIndex >= 0 ? getOptionId(activeIndex) : null"
                class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-10 sm:pr-4 text-sm text-gray-800 placeholder-gray-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder-gray-500 dark:focus:border-brand-400"
            />

            <span
                x-show="isLoading"
                x-cloak
                class="absolute inset-y-0 right-3 flex items-center text-gray-400 sm:right-3"
                :class="isExpanded ? 'right-9 sm:right-3' : 'right-3'"
            >
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </div>

        <div
            x-ref="dropdown"
            x-show="isOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            id="search-results-listbox"
            role="listbox"
            aria-label="Search results"
            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            {{-- Error state --}}
            <template x-if="errorMessage">
                <div class="px-4 py-3 text-sm text-red-600 dark:text-red-400" x-text="errorMessage"></div>
            </template>

            {{-- No results state --}}
            <template x-if="hasNoResults && query.length >= 2">
                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    No results found.
                </div>
            </template>

            {{-- Grouped results --}}
            <template x-for="(group, groupIdx) in groups" :key="group.key">
                <div>
                    <div class="sticky top-0 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-700/50 dark:text-gray-400" role="presentation" x-text="group.label"></div>

                    <template x-for="(item, itemIdx) in group.items" :key="item.id">
                        <a
                            :id="getOptionId(getFlatIndex(groupIdx, itemIdx))"
                            :href="getItemUrl(group.key, item)"
                            @click.prevent="navigateTo(getItemUrl(group.key, item))"
                            @mouseenter="activeIndex = getFlatIndex(groupIdx, itemIdx)"
                            role="option"
                            :aria-selected="activeIndex === getFlatIndex(groupIdx, itemIdx)"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300"
                            :class="activeIndex === getFlatIndex(groupIdx, itemIdx) ? 'bg-gray-100 dark:bg-gray-700/50' : ''"
                        >
                            <span x-text="getItemLabel(item)" class="truncate"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
