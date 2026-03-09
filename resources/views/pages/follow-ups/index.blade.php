@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Follow-ups" />

    {{-- Filter bar --}}
    <div class="mb-6">
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

    {{-- Toolbar --}}
    <div class="mb-6 flex items-center justify-end">
        @include('partials.follow-up-create-modal', [
            'teamOptions' => $teamOptions,
            'memberOptions' => $memberOptions,
        ])
    </div>

    {{-- Results --}}
    <div id="follow-ups-results">
        @include('partials.follow-ups-list', ['sections' => $sections])
    </div>
@endsection
