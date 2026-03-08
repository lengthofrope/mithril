@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Analytics" />

    {{-- Header with Add Widget button --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Configurable charts for your team data.
            </p>
        </div>
        <button
            type="button"
            x-data
            @click="$dispatch('open-widget-configurator')"
            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add widget
        </button>
    </div>

    {{-- Widget grid --}}
    <div
        x-data="analyticsBoard({
            context: 'analytics',
            reorderEndpoint: '{{ route('reorder') }}',
            widgetEndpoint: '{{ route('analytics.widgets.store') }}'
        })"
    >
        @if($widgets->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-white/[0.03]">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M3 3h18v18H3z" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 9h18M9 21V9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No widgets yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Click "Add widget" to get started.</p>
            </div>
        @else
            <div
                x-ref="widgetGrid"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                @foreach($widgets as $widget)
                    <x-tl.analytics-widget :widget="$widget" context="analytics" />
                @endforeach
            </div>
        @endif

        {{-- Reorder error --}}
        <div
            x-show="hasReorderError"
            x-cloak
            class="mt-2 text-xs text-red-600 dark:text-red-400"
            aria-live="assertive"
        >
            Failed to save new order. Please try again.
        </div>
    </div>

    {{-- Widget Configurator Modal --}}
    <div
        x-data="widgetConfigurator({
            storeEndpoint: '{{ route('analytics.widgets.store') }}',
            dataSources: @js(collect($dataSources)->map(fn($ds) => [
                'value' => $ds->value,
                'label' => $ds->label(),
                'allowedChartTypes' => collect($ds->allowedChartTypes())->map(fn($ct) => $ct->value)->values()->all(),
            ])->values()->all())
        })"
        @open-widget-configurator.window="open()"
    >
        {{-- Backdrop --}}
        <div
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-gray-900/50"
            @click="close()"
            x-cloak
        ></div>

        {{-- Modal panel --}}
        <div
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-cloak
        >
            <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-900" @click.stop>
                <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">Add Widget</h2>

                {{-- Data source select --}}
                <div class="mb-4">
                    <label for="widget-source" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Data Source</label>
                    <select
                        id="widget-source"
                        x-model="selectedSource"
                        @change="onSourceChange()"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="">Select a data source...</option>
                        <template x-for="source in dataSources" :key="source.value">
                            <option :value="source.value" x-text="source.label"></option>
                        </template>
                    </select>
                </div>

                {{-- Chart type select --}}
                <div class="mb-4">
                    <label for="widget-chart-type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Chart Type</label>
                    <select
                        id="widget-chart-type"
                        x-model="selectedChartType"
                        :disabled="availableChartTypes.length === 0"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="">Select chart type...</option>
                        <template x-for="ct in availableChartTypes" :key="ct">
                            <option :value="ct" x-text="ct.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())"></option>
                        </template>
                    </select>
                </div>

                {{-- Column span --}}
                <div class="mb-4">
                    <label for="widget-span" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Width</label>
                    <select
                        id="widget-span"
                        x-model.number="selectedColumnSpan"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="1">1/3 width</option>
                        <option value="2">2/3 width</option>
                        <option value="3">Full width</option>
                    </select>
                </div>

                {{-- Placement toggles --}}
                <div class="mb-6 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="showOnAnalytics" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                        Show on Analytics
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="showOnDashboard" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                        Show on Dashboard
                    </label>
                </div>

                {{-- Error --}}
                <div x-show="hasError" x-cloak class="mb-4 text-sm text-red-600 dark:text-red-400">
                    Failed to create widget. Please try again.
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="close()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="save()"
                        :disabled="!selectedSource || !selectedChartType || isSaving"
                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50 dark:hover:bg-blue-500"
                    >
                        <svg x-show="isSaving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Create Widget
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
