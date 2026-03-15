<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#3d8b6b">

    <title>@yield('title') | Mithril</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @vite(['resources/css/app.css'])

    <script>
        (function() {
            var savedTheme = localStorage.getItem('theme');
            if (savedTheme !== 'light') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>

<body class="min-h-screen login-atmosphere">

    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
        <img src="/images/decor/arch-top.svg" alt="" class="absolute top-0 left-1/2 -translate-x-1/2 w-[40rem] opacity-[0.08] dark:opacity-[0.12]" loading="lazy">
        <img src="/images/decor/fern-top-right.svg" alt="" class="absolute -top-8 -right-8 h-[24rem] w-auto opacity-[0.06] dark:opacity-[0.09]" loading="lazy">
        <img src="/images/decor/vine-bottom-left.svg" alt="" class="absolute -bottom-4 -left-4 h-[22rem] w-auto opacity-[0.06] dark:opacity-[0.09]" loading="lazy">
    </div>

    <div class="relative z-1 flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">

        <div class="w-full max-w-md">

            <div class="mb-8 flex justify-center scale-150 origin-center">
                <x-tl.logo />
            </div>

            <div class="elvish-card rounded-2xl border border-gray-200 bg-white px-6 py-8 text-center shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:px-8">

                <p class="font-heading text-6xl font-bold text-brand-500">
                    @yield('code')
                </p>

                <h1 class="mt-4 font-heading text-xl font-semibold text-gray-900 dark:text-white">
                    @yield('title')
                </h1>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    @yield('message')
                </p>

                <div class="elvish-divider mt-6">
                    <span class="elvish-divider-leaf"></span>
                </div>

                <div class="mt-6">
                    <a
                        href="{{ url('/') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Back to home
                    </a>
                </div>

            </div>

        </div>

    </div>

</body>

</html>
