<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | Mithril</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#3d8b6b">

    <!-- Preload custom fonts to prevent FOUT during view transitions -->
    <link rel="preload" as="font" type="font/woff2" href="/fonts/outfit/outfit-latin.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/philosopher/philosopher-400-latin.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/philosopher/philosopher-700-latin.woff2" crossorigin>

    <!-- Block rendering until main content is in the DOM (prevents FOUC during view transitions) -->
    <link rel="expect" blocking="render" href="#app-content">

    <meta name="sidebar-collapsed" content="{{ auth()->user()->sidebar_collapsed ? '1' : '0' }}">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <!-- Apply dark mode class on <html> before body renders to prevent flash -->
    <script nonce="{{ Vite::cspNonce() }}">(function(){var s=localStorage.getItem('theme');if(s!=='light')document.documentElement.classList.add('dark');})()</script>

</head>

<body x-data>


    @include('layouts.partials.background-decor')

    <div id="app-content" class="min-h-screen xl:flex">
        <div x-data="keyboardShortcuts()" class="hidden" aria-hidden="true"></div>
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="min-w-0 flex-1"
            x-init="requestAnimationFrame(() => $el.classList.add('transition-all', 'duration-300', 'ease-in-out'))"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded,
                'xl:ml-[90px]': !$store.sidebar.isExpanded,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @include('layouts.partials.system-notifications')
                @yield('content')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

</html>
