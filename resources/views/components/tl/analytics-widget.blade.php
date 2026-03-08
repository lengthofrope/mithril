@props(['widget', 'context' => 'analytics'])

@php
    $columnClasses = match((int) $widget->column_span) {
        2 => 'sm:col-span-2',
        3 => 'sm:col-span-2 xl:col-span-3',
        default => '',
    };
    $displayTitle = $widget->title ?? $widget->data_source->label();
@endphp

<div
    data-widget-id="{{ $widget->id }}"
    class="{{ $columnClasses }} rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    x-data="analyticsChart({
        widgetId: {{ $widget->id }},
        chartType: '{{ $widget->chart_type->value }}',
        dataSource: '{{ $widget->data_source->value }}',
        dataEndpoint: '{{ route('analytics.widget-data') }}',
        title: '{{ $displayTitle }}'
    })"
>
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
        {{-- Drag handle + title --}}
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="drag-handle shrink-0 cursor-grab touch-none text-gray-300 transition hover:text-gray-500 active:cursor-grabbing dark:text-gray-600 dark:hover:text-gray-400"
                aria-label="Drag to reorder"
                tabindex="-1"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                    <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                    <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                </svg>
            </button>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                {{ $displayTitle }}
            </h3>
        </div>

        {{-- Kebab menu --}}
        <div class="relative" x-data="{ menuOpen: false }">
            <button
                type="button"
                @click="menuOpen = !menuOpen"
                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                aria-label="Widget options"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                </svg>
            </button>

            {{-- Dropdown --}}
            <div
                x-show="menuOpen"
                @click.outside="menuOpen = false"
                x-transition
                x-cloak
                class="absolute right-0 z-10 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                {{-- Chart type options --}}
                @foreach(\App\Enums\ChartType::cases() as $ct)
                    @if(in_array($ct, $widget->data_source->allowedChartTypes()))
                        <button
                            type="button"
                            @click="updateChartType('{{ $ct->value }}'); menuOpen = false"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            @if($widget->chart_type === $ct)
                                <svg class="h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                <span class="h-4 w-4"></span>
                            @endif
                            {{ ucwords(str_replace('_', ' ', $ct->value)) }}
                        </button>
                    @endif
                @endforeach

                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                {{-- Delete --}}
                <button
                    type="button"
                    @click="$dispatch('delete-widget', { widgetId: {{ $widget->id }} }); menuOpen = false"
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete widget
                </button>
            </div>
        </div>
    </div>

    {{-- Chart area --}}
    <div class="relative px-5 py-4">
        {{-- Loading skeleton (overlays chart container) --}}
        <div
            x-show="isLoading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-white dark:bg-white/[0.03]"
        >
            <svg class="h-8 w-8 animate-spin text-gray-300 dark:text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>

        {{-- Error state (overlays chart container) --}}
        <div
            x-show="hasError"
            x-cloak
            class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white text-gray-400 dark:bg-white/[0.03] dark:text-gray-500"
        >
            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <p class="mt-2 text-sm">Failed to load chart data.</p>
        </div>

        {{-- Chart container (always visible so ApexCharts can measure dimensions) --}}
        <div x-ref="chart" class="min-h-64"></div>
    </div>
</div>
