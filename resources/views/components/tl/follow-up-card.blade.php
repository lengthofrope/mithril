@props(['followUp'])

@php
    $now = \Carbon\Carbon::now()->startOfDay();
    $dueDate = \Carbon\Carbon::parse($followUp->follow_up_date)->startOfDay();

    $dateColorClass = match(true) {
        $dueDate->isPast() && !$dueDate->isToday() => 'text-red-600 dark:text-red-400',
        $dueDate->isToday()                         => 'text-orange-600 dark:text-orange-400',
        default                                     => 'text-green-600 dark:text-green-500',
    };

    $dateLabel = match(true) {
        $dueDate->isToday()    => 'Today',
        $dueDate->isTomorrow() => 'Tomorrow',
        $dueDate->isPast()     => 'Overdue · ' . $dueDate->format('d M Y'),
        default                => $dueDate->format('d M Y'),
    };
@endphp

<div
    class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"
    role="listitem"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                {{ $followUp->description }}
            </p>

            @if($followUp->waiting_on)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Waiting on: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $followUp->waiting_on }}</span>
                </p>
            @endif
        </div>

        <span class="shrink-0 text-xs font-medium {{ $dateColorClass }}">
            {{ $dateLabel }}
        </span>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <form
            method="POST"
            action="{{ route('follow-ups.done', $followUp->id) }}"
            class="inline"
        >
            @csrf
            @method('PATCH')
            <button
                type="submit"
                class="flex items-center gap-1 rounded-lg border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 transition hover:bg-green-100 dark:border-green-700/50 dark:bg-green-500/10 dark:text-green-400 dark:hover:bg-green-500/20"
            >
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Done
            </button>
        </form>

        <div
            x-data="{ open: false }"
            class="relative"
        >
            <button
                type="button"
                x-on:click="open = !open"
                class="flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
            >
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Snooze
                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div
                x-show="open"
                x-cloak
                x-on:click.outside="open = false"
                class="absolute left-0 top-full z-10 mt-1 w-36 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                role="menu"
            >
                @foreach([['label' => '+1 day', 'days' => 1], ['label' => '+3 days', 'days' => 3], ['label' => '+1 week', 'days' => 7]] as $snooze)
                    <form method="POST" action="{{ route('follow-ups.snooze', $followUp->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="days" value="{{ $snooze['days'] }}">
                        <button
                            type="submit"
                            class="block w-full px-3 py-2 text-left text-xs text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                            role="menuitem"
                        >
                            {{ $snooze['label'] }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('follow-ups.convert', $followUp->id) }}"
            class="inline"
        >
            @csrf
            <button
                type="submit"
                class="flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
            >
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Convert to task
            </button>
        </form>
    </div>
</div>
