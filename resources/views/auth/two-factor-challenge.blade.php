<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d8b6b">

    <title>{{ $title ?? 'Two-Factor Challenge' }} | Mithril</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.json">

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <script>
        (function() {
            var savedTheme = localStorage.getItem('theme');
            var systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if ((savedTheme || systemTheme) === 'dark') {
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

            <div class="mb-8 text-center">
                <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
                    Mithril
                </h1>
                <p class="mt-1 text-sm italic text-gray-500 dark:text-gray-400">
                    Lightweight armor for team leads
                </p>
                <div class="elvish-divider mt-4">
                    <span class="elvish-divider-leaf"></span>
                </div>
            </div>

            <div x-data="{ mode: 'code' }" class="elvish-card rounded-2xl border border-gray-200 bg-white px-6 py-8 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:px-8">

                <div class="mb-6 text-center">
                    <svg class="mx-auto mb-3 h-12 w-12 text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Two-factor authentication
                    </h2>
                    <p x-show="mode === 'code'" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Enter the 6-digit code from your authenticator app.
                    </p>
                    <p x-show="mode === 'recovery'" x-cloak class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Enter one of your recovery codes.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20" role="alert" aria-live="polite">
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">
                            {{ $errors->first() }}
                        </p>
                    </div>
                @endif

                <form x-show="mode === 'code'" method="POST" action="{{ route('two-factor.challenge') }}" novalidate>
                    @csrf

                    <div class="space-y-5">
                        <div>
                            <label for="code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Authentication code
                            </label>
                            <input
                                id="code"
                                type="text"
                                name="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                autofocus
                                maxlength="6"
                                pattern="[0-9]{6}"
                                required
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-center text-lg tracking-[0.3em] text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-500 dark:focus:border-brand-500"
                                placeholder="000000"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        >
                            Verify
                        </button>
                    </div>
                </form>

                <form x-show="mode === 'recovery'" x-cloak method="POST" action="{{ route('two-factor.challenge') }}" novalidate>
                    @csrf

                    <div class="space-y-5">
                        <div>
                            <label for="recovery_code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Recovery code
                            </label>
                            <input
                                id="recovery_code"
                                type="text"
                                name="recovery_code"
                                required
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-500 dark:focus:border-brand-500"
                                placeholder="xxxxx-xxxxx"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        >
                            Verify
                        </button>
                    </div>
                </form>

                <div class="mt-5 text-center">
                    <button
                        x-show="mode === 'code'"
                        x-on:click="mode = 'recovery'"
                        type="button"
                        class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        Use a recovery code
                    </button>
                    <button
                        x-show="mode === 'recovery'"
                        x-cloak
                        x-on:click="mode = 'code'"
                        type="button"
                        class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        Use authenticator code
                    </button>
                </div>

                <div class="mt-4 text-center">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-400">
                            Sign out
                        </button>
                    </form>
                </div>

            </div>

        </div>

    </div>

</body>

</html>
