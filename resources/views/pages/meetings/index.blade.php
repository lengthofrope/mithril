@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Meetings" />

    {{-- Filter bar + toolbar --}}
    <div class="relative mb-6">
        <x-tl.filter-bar
            :endpoint="route('meetings.index')"
            results-selector="#meetings-results"
            :filters="[
                ['field' => 'team_id', 'type' => 'select', 'label' => 'Team', 'options' => $teamOptions],
                ['field' => 'team_member_id', 'type' => 'select', 'label' => 'Member', 'options' => $memberOptions, 'linked_to' => 'team_id'],
                ['field' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => $typeOptions],
                ['field' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => $statusOptions],
                ['field' => 'show_past', 'type' => 'boolean', 'label' => 'Show past'],
            ]"
        />

        <div class="absolute right-0 top-0">
            @include('partials.meeting-create-modal', [
                'teamOptions' => $teamOptions,
                'memberOptions' => $memberOptions,
            ])
        </div>
    </div>

    {{-- Meetings list --}}
    <div id="meetings-results">
        @include('partials.meetings-list', [
            'upcomingMeetings' => $upcomingMeetings,
            'pastMeetings' => $pastMeetings,
        ])
    </div>
@endsection
