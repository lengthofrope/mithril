@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    <div class="space-y-6 max-w-2xl">

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

    </div>
@endsection
