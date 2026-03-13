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
    <link rel="preload" as="font" type="font/woff2" href="/fonts/cormorant-garamond/cormorant-garamond-latin.woff2" crossorigin>

    <!-- Block rendering until main content is in the DOM (prevents FOUC during view transitions) -->
    <link rel="expect" blocking="render" href="#app-content">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    this.theme = savedTheme ?? (systemTheme === 'light' ? 'light' : 'dark');
                    this.updateTheme();
                },
                theme: 'dark',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                sidebarCollapsed: {{ auth()->user()->sidebar_collapsed ? 'true' : 'false' }},
                isExpanded: window.innerWidth >= 1280 && !{{ auth()->user()->sidebar_collapsed ? 'true' : 'false' }},
                isMobileOpen: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    this.isMobileOpen = false;
                    this.persistCollapsed(!this.isExpanded);
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                persistCollapsed(collapsed) {
                    fetch('/settings/sidebar-collapsed', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ sidebar_collapsed: collapsed }),
                    });
                }
            });
        });
    </script>

    <!-- Apply dark mode class on <html> before body renders to prevent flash -->
    <script>
        (function() {
            var savedTheme = localStorage.getItem('theme');
            var systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if (savedTheme === 'dark' || (!savedTheme && systemTheme !== 'light')) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Set view transition click origin (must be in <head> as parser-blocking script) -->
    <script>
        window.addEventListener('pagereveal', function(e) {
            if (!e.viewTransition) return;
            var x = sessionStorage.getItem('click-x') || '50%';
            var y = sessionStorage.getItem('click-y') || '50%';
            document.documentElement.style.setProperty('--click-x', x);
            document.documentElement.style.setProperty('--click-y', y);
        });
    </script>
    
</head>

<body
    x-data
    x-init="
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = !$store.sidebar.sidebarCollapsed;
        }
    };
    window.addEventListener('resize', checkMobile);">


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
                @yield('content')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js').catch(function() {});
        });
    }

    document.addEventListener('click', function(e) {
        var x = e.clientX;
        var y = e.clientY;
        if (!x && !y) {
            var btn = e.target.closest('button, a, [type="submit"]');
            if (btn) {
                var rect = btn.getBoundingClientRect();
                x = rect.left + rect.width / 2;
                y = rect.top + rect.height / 2;
            }
        }
        sessionStorage.setItem('click-x', (x / window.innerWidth * 100).toFixed(1) + '%');
        sessionStorage.setItem('click-y', (y / window.innerHeight * 100).toFixed(1) + '%');
    });
</script>

</html>
