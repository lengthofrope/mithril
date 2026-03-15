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
                <div class="ml-auto flex items-center gap-3">
                    <a
                        href="https://github.com/lengthofrope/mithril"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        title="View on GitHub"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
                        </svg>
                    </a>
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        v{{ $currentVersion }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Development --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Development</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Lead developer</p>
                        <a
                            href="https://www.basdekort.nl/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"
                        >
                            Bas de Kort
                        </a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Funded with the support of</p>
                        <a
                            href="https://www.proudnerds.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"
                        >
                            Proud Nerds
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- License --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">License</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                This project is open source and available under the
                <span class="font-medium text-gray-900 dark:text-white">MIT License</span>.
            </p>
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

            @if($hasMoreReleases)
                <div class="border-t border-gray-100 px-6 py-4 text-center dark:border-gray-800">
                    <a
                        href="https://github.com/lengthofrope/mithril/blob/main/CHANGELOG.md"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"
                    >
                        View full changelog on GitHub
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
