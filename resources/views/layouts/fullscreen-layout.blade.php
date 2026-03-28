<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | Mithril</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <!-- Apply dark mode class on <html> before body renders to prevent flash -->
    <script nonce="{{ Vite::cspNonce() }}">(function(){var s=localStorage.getItem('theme');if(s!=='light')document.documentElement.classList.add('dark');})()</script>
</head>

<body x-data>

    @yield('content')

</body>

@stack('scripts')

</html>
