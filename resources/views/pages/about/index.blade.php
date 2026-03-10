@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="About" />

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- App info card --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-4">
                <img
                    src="{{ asset('images/logo/logo-icon.svg') }}"
                    alt="Mithril logo"
                    class="h-12 w-12"
                >
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ config('app.name') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Lightweight armor for team leads
                    </p>
                </div>
                <span class="ml-auto rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    v{{ $currentVersion }}
                </span>
            </div>
        </div>

        {{-- Changelog --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Changelog</h2>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($releases as $release)
                    <div class="px-6 py-4" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between text-left"
                            @click="open = !open"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    v{{ $release['version'] }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $release['date'] }}
                                </span>
                            </div>
                            <svg
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': open }"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            x-cloak
                            class="mt-4 space-y-4"
                        >
                            @foreach($release['sections'] as $sectionName => $items)
                                <div>
                                    @switch(strtolower($sectionName))
                                        @case('added')
                                            <span class="bg-green-200 text-green-800 dark:bg-green-500/15 dark:text-green-400 mb-2 inline-block rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider">{{ $sectionName }}</span>
                                            @break
                                        @case('changed')
                                            <span class="bg-blue-200 text-blue-800 dark:bg-blue-500/15 dark:text-blue-400 mb-2 inline-block rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider">{{ $sectionName }}</span>
                                            @break
                                        @case('fixed')
                                            <span class="bg-orange-200 text-orange-800 dark:bg-orange-500/15 dark:text-orange-400 mb-2 inline-block rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider">{{ $sectionName }}</span>
                                            @break
                                        @case('security')
                                            <span class="bg-red-200 text-red-800 dark:bg-red-500/15 dark:text-red-400 mb-2 inline-block rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider">{{ $sectionName }}</span>
                                            @break
                                        @default
                                            <span class="bg-gray-200 text-gray-800 dark:bg-gray-500/15 dark:text-gray-400 mb-2 inline-block rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider">{{ $sectionName }}</span>
                                    @endswitch
                                    <ul class="space-y-3">
                                        @foreach($items as $item)
                                            <li class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                                {!! \Illuminate\Support\Str::of($item)
                                                    ->replaceMatches('/\*\*(.+?)\*\*\s*—\s*/', '<strong class="block font-medium text-gray-900 dark:text-white/90">$1</strong>')
                                                    ->replaceMatches('/\*\*(.+?)\*\*/', '<strong class="block font-medium text-gray-900 dark:text-white/90">$1</strong>') !!}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
