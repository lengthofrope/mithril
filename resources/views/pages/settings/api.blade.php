@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    <div
        x-data="apiTokenManager({
            tokens: @js($tokens->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'created_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()),
            scopeAbilityMap: @js($scopeAbilityMap),
            allAbilities: @js(collect(\App\Enums\ApiAbility::cases())->map(fn ($a) => $a->value)->values()),
            groupedAbilities: @js(collect($groupedAbilities)->map(fn ($abilities) => collect($abilities)->map(fn ($a) => $a->value)->values())),
            storeUrl: @js(route('settings.storeToken')),
            destroyUrl: @js(route('settings.destroyToken', ['tokenId' => '__ID__'])),
            destroyAllUrl: @js(route('settings.destroyAllTokens')),
        })"
        class="space-y-6"
    >

        {{-- Token creation --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Create API token</h2>
            </div>
            <div class="p-5 space-y-5">

                {{-- Plaintext token display --}}
                <template x-if="plaintextToken">
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                        <p class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                            Token created successfully
                        </p>
                        <p class="mb-3 text-xs text-amber-700 dark:text-amber-400">
                            Copy this token now. You will not be able to see it again.
                        </p>
                        <div class="flex items-center gap-2">
                            <code
                                x-text="plaintextToken"
                                class="flex-1 break-all rounded bg-white px-3 py-2 text-xs font-mono text-gray-800 dark:bg-gray-900 dark:text-gray-200"
                            ></code>
                            <button
                                type="button"
                                x-on:click="copyToken()"
                                class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                aria-label="Copy token to clipboard"
                            >
                                <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Token name --}}
                <div>
                    <label for="token-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Token name
                    </label>
                    <input
                        id="token-name"
                        type="text"
                        x-model="form.name"
                        maxlength="100"
                        placeholder="e.g. CI pipeline, Zapier integration"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 transition focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder-gray-500"
                    />
                    <template x-if="errors.name">
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="errors.name[0]"></p>
                    </template>
                </div>

                {{-- Scope tier selector --}}
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Access tier</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach($scopes as $scope)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                   x-bind:class="selectedScope === @js($scope->value)
                                       ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-900/20 dark:text-brand-300'
                                       : 'border-gray-300 text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600'"
                            >
                                <input
                                    type="radio"
                                    name="scope"
                                    value="{{ $scope->value }}"
                                    x-model="selectedScope"
                                    x-on:change="applyScope(@js($scope->value))"
                                    class="sr-only"
                                />
                                <span>{{ ucwords(str_replace('-', ' ', $scope->value)) }}</span>
                            </label>
                        @endforeach
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                               x-bind:class="selectedScope === 'custom'
                                   ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-900/20 dark:text-brand-300'
                                   : 'border-gray-300 text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600'"
                        >
                            <input
                                type="radio"
                                name="scope"
                                value="custom"
                                x-model="selectedScope"
                                class="sr-only"
                            />
                            <span>Custom</span>
                        </label>
                    </div>
                    <template x-if="errors.scope">
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="errors.scope[0]"></p>
                    </template>
                </fieldset>

                {{-- Per-resource abilities --}}
                <div x-show="selectedScope === 'custom' || selectedScope !== ''" x-collapse>
                    <button
                        type="button"
                        x-on:click="showAbilities = !showAbilities"
                        class="mb-3 flex items-center gap-1 text-xs font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <svg class="h-3.5 w-3.5 transition-transform" x-bind:class="showAbilities ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                        <span x-text="showAbilities ? 'Hide granular abilities' : 'Show granular abilities'">Show granular abilities</span>
                    </button>
                    <div x-show="showAbilities" x-collapse class="space-y-4">
                        @foreach($groupedAbilities as $resource => $abilities)
                            <div>
                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ ucwords(str_replace('-', ' ', $resource)) }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($abilities as $ability)
                                        <label class="flex cursor-pointer items-center gap-1.5 rounded border px-2 py-1 text-xs transition"
                                               x-bind:class="form.abilities.includes(@js($ability->value))
                                                   ? 'border-brand-400 bg-brand-50 text-brand-700 dark:border-brand-500 dark:bg-brand-900/20 dark:text-brand-300'
                                                   : 'border-gray-200 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600'"
                                        >
                                            <input
                                                type="checkbox"
                                                value="{{ $ability->value }}"
                                                x-on:change="toggleAbility(@js($ability->value))"
                                                x-bind:checked="form.abilities.includes(@js($ability->value))"
                                                class="sr-only"
                                            />
                                            <span>{{ explode(':', $ability->value)[1] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Error summary --}}
                <template x-if="errorMessage">
                    <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMessage"></p>
                </template>

                {{-- Create button --}}
                <div>
                    <button
                        type="button"
                        x-on:click="createToken()"
                        x-bind:disabled="isSubmitting"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-50 dark:bg-brand-600 dark:hover:bg-brand-500"
                    >
                        <span x-show="!isSubmitting">Create token</span>
                        <span x-show="isSubmitting" x-cloak>Creating...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Token list --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Active tokens</h2>
                <button
                    type="button"
                    x-show="tokens.length > 0"
                    x-on:click="confirmRevokeAll()"
                    class="text-xs font-medium text-red-600 transition hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                >
                    Revoke all
                </button>
            </div>
            <div class="p-5">
                <template x-if="tokens.length === 0">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No active tokens.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="token in tokens" x-bind:key="token.id">
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="token.name"></p>
                                <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="getScopeDescription(token.abilities)"></span>
                                    <span x-text="'Created ' + formatDate(token.created_at)"></span>
                                    <span x-text="token.last_used_at ? 'Last used ' + formatDate(token.last_used_at) : 'Never used'"></span>
                                </div>
                            </div>
                            <button
                                type="button"
                                x-on:click="confirmRevoke(token.id, token.name)"
                                class="ml-3 shrink-0 text-xs font-medium text-red-600 transition hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Revoke
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Available abilities reference --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Available abilities</h2>
            </div>
            <div class="p-5 space-y-4">
                @foreach($groupedAbilities as $resource => $abilities)
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ ucwords(str_replace('-', ' ', $resource)) }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($abilities as $ability)
                                <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $ability->value }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Confirmation dialog --}}
        <div
            x-show="confirmDialog.show"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="confirmDialog.show = false"
        >
            <div
                class="mx-4 w-full max-w-sm rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                x-on:click.outside="confirmDialog.show = false"
            >
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="confirmDialog.title"></h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="confirmDialog.message"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        x-on:click="confirmDialog.show = false"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="confirmDialog.onConfirm(); confirmDialog.show = false"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700"
                    >
                        Revoke
                    </button>
                </div>
            </div>
        </div>

    </div>
@endsection
