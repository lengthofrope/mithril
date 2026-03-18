@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Meetings" />

    {{-- Filter bar --}}
    <div class="mb-6">
        <x-tl.filter-bar
            :endpoint="route('meetings.index')"
            results-selector="#meetings-results"
            :filters="[
                ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
                ['field' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => $typeOptions],
                ['field' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => $statusOptions],
            ]"
        />
    </div>

    {{-- Toolbar --}}
    <div class="mb-6 flex items-center justify-end">
        @include('partials.meeting-create-modal', [
            'teamOptions' => $teamOptions,
            'memberOptions' => $memberOptions,
        ])
    </div>

    {{-- Meetings list --}}
    <div id="meetings-results">
        @include('partials.meetings-list', [
            'upcomingMeetings' => $upcomingMeetings,
            'pastMeetings' => $pastMeetings,
        ])
    </div>
@endsection
