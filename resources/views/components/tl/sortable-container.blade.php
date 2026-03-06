@props([
    'modelType',
    'endpoint',
    'group' => null,
    'containerId',
])

<div
    x-data="sortableList({
        containerSelector: '#{{ $containerId }}',
        modelType: '{{ $modelType }}',
        endpoint: '{{ $endpoint }}',
        @if($group) group: '{{ $group }}', @endif
    })"
>
    <div
        id="{{ $containerId }}"
        class="space-y-2"
        role="list"
        aria-label="{{ ucfirst(str_replace('_', ' ', $modelType)) }} list"
    >
        {{ $slot }}
    </div>

    <div
        x-show="hasReorderError"
        x-cloak
        class="mt-2 text-xs text-red-600 dark:text-red-400"
        aria-live="assertive"
    >
        Failed to save new order. Please try again.
    </div>
</div>
