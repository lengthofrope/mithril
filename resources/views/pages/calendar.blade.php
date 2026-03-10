@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Calendar" />

    <x-tl.calendar-events :events="$calendarEvents" :isMicrosoftConnected="$isMicrosoftConnected" :timezone="$userTimezone" />
@endsection
