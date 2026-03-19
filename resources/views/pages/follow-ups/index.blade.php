@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Follow-ups" />

    {{-- Filter bar + toolbar --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <x-tl.filter-bar
                :endpoint="route('follow-ups.index')"
                results-selector="#follow-ups-results"
                :filters="[
                    ['field' => 'search', 'type' => 'search', 'label' => 'Search'],
                    ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                    ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
                ]"
            />
        </div>

        @include('partials.follow-up-create-modal', [
            'teamOptions' => $teamOptions,
            'memberOptions' => $memberOptions,
        ])
    </div>

    {{-- Results --}}
    <div x-data="refreshable({ url: '{{ route('partials.follow-ups') }}', topics: ['follow_ups'], pollInterval: 30000 })">
        <div id="follow-ups-results" data-refresh-target>
            @include('partials.follow-ups-list', ['sections' => $sections])
        </div>
    </div>
@endsection
