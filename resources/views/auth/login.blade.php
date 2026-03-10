<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d8b6b">

    <title>{{ $title ?? 'Sign In' }} | Mithril</title>

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

            <div class="elvish-card rounded-2xl border border-gray-200 bg-white px-6 py-8 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:px-8">

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20" role="alert" aria-live="polite">
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">
                            {{ $errors->first() }}
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="space-y-5">

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="email"
                                autofocus
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-500 dark:focus:border-brand-500 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                placeholder="you@example.com"
                                aria-describedby="{{ $errors->has('email') ? 'email-error' : null }}"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            >
                            @error('email')
                                <p id="email-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:placeholder-gray-500 dark:focus:border-brand-500 @error('password') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                placeholder="••••••••"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            >
                            @error('password')
                                <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <input
                                id="remember"
                                type="checkbox"
                                name="remember"
                                {{ old('remember') ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/[0.03]"
                            >
                            <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Remember me
                            </label>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        >
                            Sign in
                        </button>

                    </div>
                </form>

            </div>

        </div>

    </div>

</body>

<script>
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

    window.addEventListener('pagereveal', function(e) {
        if (!e.viewTransition) return;
        var x = sessionStorage.getItem('click-x') || '50%';
        var y = sessionStorage.getItem('click-y') || '50%';
        document.documentElement.style.setProperty('--click-x', x);
        document.documentElement.style.setProperty('--click-y', y);
    });
</script>

</html>
