@props(['items' => null, 'pageTitle' => 'Page'])

@php
    $items = $items ?? [
        ['label' => 'Home', 'url' => '/'],
        ['label' => $pageTitle, 'url' => null],
    ];
    $lastIndex = count($items) - 1;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
        {{ $items[$lastIndex]['label'] ?? 'Page' }}
    </h2>
    <nav aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5">
            @foreach($items as $index => $crumb)
                @if($index < $lastIndex)
                    <li>
                        <a
                            class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="{{ $crumb['url'] ?? '#' }}"
                        >
                            {{ $crumb['label'] }}
                            <svg
                                class="stroke-current"
                                width="17"
                                height="16"
                                viewBox="0 0 17 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <path
                                    d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                    stroke=""
                                    stroke-width="1.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </a>
                    </li>
                @else
                    <li class="text-sm text-gray-800 dark:text-white/90">
                        {{ $crumb['label'] }}
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>
</div>
