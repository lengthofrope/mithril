@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notes" />

    {{-- Filter bar --}}
    <div class="mb-6">
        <x-tl.filter-bar
            :endpoint="route('notes.index')"
            results-selector="#notes-results"
            :filters="[
                ['field' => 'search', 'type' => 'search', 'label' => 'Search notes'],
                ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
            ]"
        />
    </div>

    {{-- Tag filters --}}
    @if($allTags->isNotEmpty())
        <div class="mb-6 flex flex-wrap items-center gap-1.5" aria-label="Filter by tag">
            @foreach($allTags as $tag)
                <a
                    href="{{ route('notes.index', ['tag' => $tag]) }}"
                    class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10"
                >
                    {{ $tag }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="mb-6 flex items-center justify-end">
        @include('partials.note-create-modal', [
            'teamOptions' => $teamOptions,
            'memberOptions' => $memberOptions,
        ])
    </div>

    {{-- Notes grid --}}
    <div id="notes-results">
        @include('partials.notes-list', ['notes' => $notes])
    </div>
@endsection
