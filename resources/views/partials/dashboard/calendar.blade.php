@if($isMicrosoftConnected)
    <x-tl.calendar-upcoming :events="$calendarEvents" :timezone="$userTimezone" />
@endif
