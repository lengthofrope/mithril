@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Bilas" />

    {{-- Upcoming bilas --}}
    <div class="mb-8">
        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Upcoming</h2>

        @if($upcomingBilas->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($upcomingBilas as $bila)
                    <a
                        href="{{ route('bilas.show', $bila->id) }}"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.06] dark:hover:border-gray-700"
                    >
                        @if(isset($bila->member) && $bila->member)
                            <x-tl.team-member-avatar :member="$bila->member" size="md" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $bila->member->name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($bila->scheduled_date)->format('d F Y') }}
                                </p>
                                @if($bila->member->role)
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $bila->member->role }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    Bila #{{ $bila->id }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($bila->scheduled_date)->format('d F Y') }}
                                </p>
                            </div>
                        @endif

                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @else
            <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400 dark:border-gray-700 dark:text-gray-500">
                No upcoming bilas scheduled.
            </p>
        @endif
    </div>

    {{-- Past bilas --}}
    <div>
        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Past bilas</h2>

        @if($pastBilas->isNotEmpty())
            <div class="space-y-2">
                @foreach($pastBilas as $bila)
                    <a
                        href="{{ route('bilas.show', $bila->id) }}"
                        class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-gray-800 dark:bg-white/[0.06] dark:hover:border-gray-700"
                    >
                        @if(isset($bila->member) && $bila->member)
                            <x-tl.team-member-avatar :member="$bila->member" size="sm" />
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $bila->member->name }}
                                </span>
                            </div>
                        @else
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Bila #{{ $bila->id }}</span>
                            </div>
                        @endif

                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($bila->scheduled_date)->format('d M Y') }}
                        </span>

                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @else
            <p class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400 dark:border-gray-700 dark:text-gray-500">
                No past bilas found.
            </p>
        @endif
    </div>
@endsection
