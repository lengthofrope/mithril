@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

      <div class="space-y-6">

        {{-- Avatar --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Profile picture</h2>
            </div>
            <div class="p-5">
                <div class="flex items-center gap-5">
                    <x-tl.user-avatar :user="$user" size="2xl" />

                    <div class="flex flex-col gap-3">
                        <form
                            method="POST"
                            action="{{ route('profile.avatar.upload') }}"
                            enctype="multipart/form-data"
                            class="flex items-center gap-2"
                        >
                            @csrf
                            <label
                                for="avatar-upload"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                Upload photo
                            </label>
                            <input
                                id="avatar-upload"
                                type="file"
                                name="avatar"
                                accept="image/*"
                                required
                                class="sr-only"
                                x-data
                                x-on:change="$el.closest('form').submit()"
                            >
                        </form>

                        @if($user->avatar_path)
                            <form method="POST" action="{{ route('profile.avatar.delete') }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Remove photo
                                </button>
                            </form>
                        @endif

                        <p class="text-xs text-gray-500 dark:text-gray-400">JPG, PNG or GIF. Max 2MB.</p>

                        @error('avatar')
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Profile details --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Profile details</h2>
            </div>
            <div class="p-5">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-4">
                        <div>
                            <label for="profile-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <input
                                id="profile-name"
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                required
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="profile-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email
                            </label>
                            <input
                                id="profile-email"
                                type="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                required
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('email')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                            >
                                Save changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

      </div>

      <div class="space-y-6">

        {{-- Password --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Change password</h2>
            </div>
            <div class="p-5">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="name" value="{{ $user->name }}">
                    <input type="hidden" name="email" value="{{ $user->email }}">

                    <div class="space-y-4">
                        <div>
                            <label for="current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current password
                            </label>
                            <input
                                id="current-password"
                                type="password"
                                name="current_password"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('current_password')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="new-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                New password
                            </label>
                            <input
                                id="new-password"
                                type="password"
                                name="password"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('password')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirm new password
                            </label>
                            <input
                                id="password-confirmation"
                                type="password"
                                name="password_confirmation"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                            >
                                Update password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Two-factor authentication --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Two-factor authentication</h2>
            </div>
            <div class="p-5">
                @if($user->hasTwoFactorEnabled())
                    {{-- 2FA is enabled --}}
                    <div class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-800/50 dark:bg-green-500/10">
                        <svg class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 12 15 16 10"/>
                        </svg>
                        <span class="text-sm font-medium text-green-700 dark:text-green-400">
                            Two-factor authentication is enabled.
                        </span>
                    </div>

                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4 space-y-4">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label for="2fa-disable-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current password
                            </label>
                            <input
                                id="2fa-disable-password"
                                type="password"
                                name="current_password"
                                required
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('current_password')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/>
                            </svg>
                            Disable two-factor authentication
                        </button>
                    </form>

                @elseif(session('two_factor_setup') || ($user->two_factor_secret && !$user->two_factor_confirmed_at))
                    {{-- 2FA setup in progress — show QR code and confirmation form --}}
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.), then enter the 6-digit code to confirm.
                    </p>

                    <div class="mb-4 flex justify-center rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        {!! \App\Http\Controllers\Web\TwoFactorController::generateQrCodeSvg($user) !!}
                    </div>

                    @php
                        $recoveryCodes = $user->two_factor_recovery_codes
                            ? json_decode(decrypt($user->two_factor_recovery_codes), true)
                            : [];
                    @endphp

                    @if(!empty($recoveryCodes))
                        <div class="mb-4">
                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Recovery codes</p>
                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                Store these codes in a safe place. Each code can only be used once to regain access if you lose your authenticator device.
                            </p>
                            <div class="grid grid-cols-2 gap-1 rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                @foreach($recoveryCodes as $code)
                                    <span>{{ $code }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="2fa-confirm-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Verification code
                            </label>
                            <input
                                id="2fa-confirm-code"
                                type="text"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required
                                placeholder="000000"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 tracking-widest placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                            >
                            @error('code')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                        >
                            Confirm and enable
                        </button>
                    </form>

                @else
                    {{-- 2FA not enabled — show enable button --}}
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Add an extra layer of security to your account by enabling two-factor authentication with an authenticator app.
                    </p>

                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            Enable two-factor authentication
                        </button>
                    </form>
                @endif
            </div>
        </div>

      </div>

    </div>

    {{-- Success flash --}}
    @if(session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed bottom-6 right-6 z-50 rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-lg"
            role="alert"
        >
            {{ session('status') }}
        </div>
    @endif
@endsection
