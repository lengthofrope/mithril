@props(['status'])

@php
    $colorMap = [
        'open'        => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
        'in_progress' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
        'waiting'     => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        'done'        => 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500',
        'snoozed'     => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400',
    ];

    $labelMap = [
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'done'        => 'Done',
        'snoozed'     => 'Snoozed',
    ];

    $key = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $colorClass = $colorMap[$key] ?? 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400';
    $label = $labelMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
@endphp

<span data-status-badge class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colorClass }}">
    {{ $label }}
</span>
