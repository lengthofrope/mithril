@props(['value', 'options', 'colorMap', 'endpoint', 'field'])

@php
    $currentValue = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $optionsJson = json_encode($options, JSON_THROW_ON_ERROR);
    $colorMapJson = json_encode($colorMap, JSON_THROW_ON_ERROR);
@endphp

<div
    class="relative inline-flex"
    x-data="inlineSelect({ endpoint: '{{ $endpoint }}', field: '{{ $field }}', value: '{{ $currentValue }}', options: {{ $optionsJson }}, colorMap: {{ $colorMapJson }} })"
    @click.outside="close()"
    @keydown.escape.window="close()"
>
    <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium transition-opacity hover:opacity-80"
        :class="colorClass"
        @click.stop="toggle()"
        :aria-expanded="isOpen"
        aria-haspopup="listbox"
    >
        <span x-text="label"></span>
        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute left-0 top-full z-50 mt-1 min-w-32 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        role="listbox"
        :aria-label="'Select ' + '{{ $field }}'"
    >
        @foreach($options as $optionValue => $optionLabel)
            <button
                type="button"
                role="option"
                :aria-selected="value === '{{ $optionValue }}'"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs transition hover:bg-gray-50 dark:hover:bg-gray-700"
                :class="value === '{{ $optionValue }}' ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                @click.stop="select('{{ $optionValue }}')"
            >
                <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $colorMap[$optionValue] ?? '' }}"></span>
                {{ $optionLabel }}
            </button>
        @endforeach
    </div>
</div>
