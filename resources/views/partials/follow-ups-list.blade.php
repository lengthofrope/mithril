{{--
    Partial: follow-ups-list
    Variables:
        $sections — associative array with keys: overdue, today, this_week, later
                    Each value is a collection of FollowUp models
--}}

@php
    $sectionConfig = [
        'overdue'   => ['label' => 'Overdue',    'color' => 'red'],
        'today'     => ['label' => 'Today',       'color' => 'orange'],
        'this_week' => ['label' => 'This week',   'color' => 'yellow'],
        'later'     => ['label' => 'Later',       'color' => 'green'],
    ];

    $headerColorMap = [
        'red'    => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-900/50',
        'orange' => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-900/50',
        'yellow' => 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-400 dark:border-yellow-900/50',
        'green'  => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-500/10 dark:text-green-400 dark:border-green-900/50',
    ];
@endphp

@php $hasAny = collect($sections)->flatten()->isNotEmpty(); @endphp

@if(!$hasAny)
    <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
        <p class="text-sm text-gray-400 dark:text-gray-500">No follow-ups found.</p>
    </div>
@else
    <div class="space-y-6">
        @foreach($sectionConfig as $key => $config)
            @php $items = $sections[$key] ?? collect(); @endphp
            @if($items->isNotEmpty())
                <section aria-label="{{ $config['label'] }} follow-ups">
                    <div class="mb-3 flex items-center gap-2">
                        <h2 class="rounded-lg border px-3 py-1 text-xs font-semibold {{ $headerColorMap[$config['color']] }}">
                            {{ $config['label'] }}
                        </h2>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $items->count() }}
                        </span>
                    </div>

                    <div class="space-y-3" role="list">
                        @foreach($items as $followUp)
                            <x-tl.follow-up-card :followUp="$followUp" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@endif
