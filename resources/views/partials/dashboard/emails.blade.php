@if($isMicrosoftConnected)
    <x-tl.email-flagged-widget :emails="$flaggedEmails" />
@endif
