@props([
    'user',
    'size' => 'md',
])

@php
    $sizeMap = [
        'sm' => ['outer' => 'h-8 w-8', 'text' => 'text-xs'],
        'md' => ['outer' => 'h-10 w-10', 'text' => 'text-sm'],
        'lg' => ['outer' => 'h-12 w-12', 'text' => 'text-base'],
        'xl' => ['outer' => 'h-16 w-16', 'text' => 'text-lg'],
        '2xl' => ['outer' => 'h-24 w-24', 'text' => 'text-2xl'],
    ];

    $sizeClasses = $sizeMap[$size] ?? $sizeMap['md'];

    $initials = collect(explode(' ', $user->name))
        ->map(fn($word) => strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<div
    class="relative shrink-0 rounded-full {{ $sizeClasses['outer'] }}"
    title="{{ $user->name }}"
>
    @if($user->avatar_path)
        <img
            src="{{ asset('storage/' . $user->avatar_path) }}"
            alt="{{ $user->name }}"
            class="h-full w-full rounded-full object-cover"
        >
    @else
        <span
            class="flex h-full w-full items-center justify-center rounded-full bg-brand-500 font-semibold text-white {{ $sizeClasses['text'] }}"
            aria-hidden="true"
        >
            {{ $initials }}
        </span>
    @endif
</div>
