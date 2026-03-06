@props(['priority'])

@php
    $colorMap = [
        'urgent' => 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        'high'   => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        'normal' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
        'low'    => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
    ];

    $labelMap = [
        'urgent' => 'Urgent',
        'high'   => 'High',
        'normal' => 'Normal',
        'low'    => 'Low',
    ];

    $colorClass = $colorMap[$priority] ?? $colorMap['normal'];
    $label = $labelMap[$priority] ?? ucfirst($priority);
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize {{ $colorClass }}">
    {{ $label }}
</span>
