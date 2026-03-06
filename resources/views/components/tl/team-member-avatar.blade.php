@props([
    'member',
    'size' => 'md',
])

@php
    $sizeMap = [
        'sm' => ['outer' => 'h-8 w-8', 'text' => 'text-xs'],
        'md' => ['outer' => 'h-10 w-10', 'text' => 'text-sm'],
        'lg' => ['outer' => 'h-12 w-12', 'text' => 'text-base'],
        'xl' => ['outer' => 'h-16 w-16', 'text' => 'text-lg'],
    ];

    $sizeClasses = $sizeMap[$size] ?? $sizeMap['md'];

    $colorOptions = [
        'bg-blue-500', 'bg-purple-500', 'bg-green-500',
        'bg-orange-500', 'bg-pink-500', 'bg-teal-500',
        'bg-indigo-500', 'bg-rose-500',
    ];

    $colorClass = $colorOptions[$member->id % count($colorOptions)];

    $initials = collect(explode(' ', $member->name))
        ->map(fn($word) => strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<div
    class="relative shrink-0 rounded-full {{ $sizeClasses['outer'] }}"
    title="{{ $member->name }}"
>
    @if($member->avatar_path)
        <img
            src="{{ asset('storage/' . $member->avatar_path) }}"
            alt="{{ $member->name }}"
            class="h-full w-full rounded-full object-cover"
        >
    @else
        <span
            class="flex h-full w-full items-center justify-center rounded-full font-semibold text-white {{ $colorClass }} {{ $sizeClasses['text'] }}"
            aria-hidden="true"
        >
            {{ $initials }}
        </span>
    @endif
</div>
