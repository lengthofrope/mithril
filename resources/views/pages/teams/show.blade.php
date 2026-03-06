@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $team->name }}" />

    {{-- Team header --}}
    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div
            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-white text-lg font-bold"
            style="background-color: {{ $team->color ?? '#3b82f6' }}"
            aria-hidden="true"
        >
            {{ strtoupper(mb_substr($team->name, 0, 1)) }}
        </div>

        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $team->name }}
            </h1>
            @if($team->description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $team->description }}
                </p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $team->members->count() }} member(s)
            </span>
        </div>
    </div>

    {{-- Members grid --}}
    <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Members</h2>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($team->members->sortBy('sort_order') as $member)
            @php
                $statusColorMap = [
                    'available'           => 'bg-green-500',
                    'absent'              => 'bg-red-400',
                    'partially_available' => 'bg-yellow-500',
                ];
                $statusLabelMap = [
                    'available'           => 'Available',
                    'absent'              => 'Absent',
                    'partially_available' => 'Partially available',
                ];
                $statusKey = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                $statusColor = $statusColorMap[$statusKey] ?? 'bg-gray-400';
                $statusLabel = $statusLabelMap[$statusKey] ?? ucfirst($statusKey);
            @endphp

            <a
                href="{{ route('teams.member', [$team->id, $member->id]) }}"
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
            >
                <div class="relative shrink-0">
                    <x-tl.team-member-avatar :member="$member" size="lg" />
                    <span
                        class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white dark:border-gray-900 {{ $statusColor }}"
                        title="{{ $statusLabel }}"
                    ></span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $member->name }}
                    </p>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $member->role }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ $statusLabel }}
                    </p>
                </div>

                <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-600 dark:group-hover:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">No members in this team yet.</p>
            </div>
        @endforelse
    </div>
@endsection
