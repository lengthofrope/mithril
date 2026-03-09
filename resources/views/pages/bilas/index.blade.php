@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Bila's" />

    {{-- Filter bar --}}
    <div class="mb-6">
        <x-tl.filter-bar
            :endpoint="route('bilas.index')"
            results-selector="#bilas-results"
            :filters="[
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
            ]"
        />
    </div>

    {{-- Toolbar --}}
    <div class="mb-6 flex items-center justify-end">
        @include('partials.bila-create-modal', [
            'teamOptions' => $teamOptions,
            'memberOptions' => $memberOptions,
        ])
    </div>

    {{-- Bilas list --}}
    <div id="bilas-results">
        @include('partials.bilas-list', [
            'upcomingBilas' => $upcomingBilas,
            'pastBilas' => $pastBilas,
        ])
    </div>
@endsection
