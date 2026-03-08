@props([
    'title',
    'count',
    'color' => 'blue',
    'link'  => null,
])

@php
    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-50 dark:bg-blue-500/10', 'text' => 'text-blue-500 dark:text-blue-400'],
        'red'    => ['bg' => 'bg-red-50 dark:bg-red-500/10', 'text' => 'text-red-500 dark:text-red-400'],
        'green'  => ['bg' => 'bg-green-50 dark:bg-green-500/10', 'text' => 'text-green-600 dark:text-green-400'],
        'orange' => ['bg' => 'bg-orange-50 dark:bg-orange-500/10', 'text' => 'text-orange-500 dark:text-orange-400'],
        'purple' => ['bg' => 'bg-purple-50 dark:bg-purple-500/10', 'text' => 'text-purple-500 dark:text-purple-400'],
    ];

    $colors = $colorMap[$color] ?? $colorMap['blue'];

    $tag = $link ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($link) href="{{ $link }}" @endif
    class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.06] {{ $link ? 'transition hover:border-gray-300 dark:hover:border-gray-700' : '' }}"
>
    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $colors['bg'] }} {{ $colors['text'] }}">
        {{ $icon ?? '' }}
    </div>

    <div class="min-w-0 flex-1">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $title }}</p>
        <p class="mt-0.5 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $count }}</p>
    </div>

    @if($link)
        <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    @endif
</{{ $tag }}>
