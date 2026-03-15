@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    @php
        $endpoint = '/api/v1/follow-ups/' . $followUp->id;
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Main content --}}
        <div class="xl:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                {{-- Description --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$endpoint"
                        field="description"
                        :value="$followUp->description"
                        type="text"
                        label="Description"
                    />
                </div>

                {{-- Row: Status + Follow-up date --}}
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-tl.auto-save-field
                        :endpoint="$endpoint"
                        field="status"
                        :value="$followUp->status instanceof \BackedEnum ? $followUp->status->value : (string) $followUp->status"
                        type="select"
                        label="Status"
                        :options="$statusOptions"
                    />

                    <x-tl.auto-save-field
                        :endpoint="$endpoint"
                        field="follow_up_date"
                        :value="$followUp->follow_up_date ? $followUp->follow_up_date->format('Y-m-d') : ''"
                        type="date"
                        label="Follow-up date"
                    />
                </div>

                {{-- Row: Team + Member (linked filtering) --}}
                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2"
                    x-data="{
                        allMembers: @js($memberOptions),
                        selectedTeamId: @js((string) ($followUp->teamMember?->team_id ?? '')),
                        get filteredMemberOptions() {
                            return this.selectedTeamId
                                ? this.allMembers.filter(m => String(m.team_id) === String(this.selectedTeamId))
                                : this.allMembers;
                        },
                    }"
                >
                    {{-- Team select (display only, syncs member filter) --}}
                    <div class="flex flex-col gap-1.5">
                        <label for="team-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select
                            id="team-select"
                            x-model="selectedTeamId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            @foreach(array_merge([['value' => '', 'label' => '— None —']], $teamOptions) as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Member select (auto-saves) --}}
                    <div
                        x-data="autoSaveField({ endpoint: @js($endpoint), field: 'team_member_id' })"
                        x-init="$nextTick(() => { value = @js((string) ($followUp->team_member_id ?? '')); })"
                        class="flex flex-col gap-1.5"
                    >
                        <label for="asf-team_member_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team member</label>
                        <select
                            id="asf-team_member_id"
                            name="team_member_id"
                            x-model="value"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">— None —</option>
                            <template x-for="opt in filteredMemberOptions" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <x-tl.auto-save-status />
                    </div>
                </div>

                {{-- Waiting on --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$endpoint"
                        field="waiting_on"
                        :value="$followUp->waiting_on ?? ''"
                        type="text"
                        label="Waiting on"
                    />
                </div>

                {{-- Linked task --}}
                @if($followUp->task)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Linked task</label>
                        <a
                            href="{{ route('tasks.show', $followUp->task) }}"
                            class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white/90 dark:hover:border-blue-600 dark:hover:text-blue-400"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            {{ $followUp->task->title }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="mt-4 flex items-center gap-3">
                <a
                    href="{{ route('follow-ups.index') }}"
                    class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                >
                    &larr; Back to follow-ups
                </a>

                <div class="ml-auto flex items-center gap-2">
                    <div
                        x-data="{
                            isOpen: false,
                            isProcessing: false,
                            async doConvert() {
                                if (this.isProcessing) return;
                                this.isProcessing = true;
                                this.isOpen = false;
                                try {
                                    const response = await fetch('{{ route('follow-ups.convert', $followUp->id) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                        },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({}),
                                    });
                                    const json = await response.json();
                                    if (json.success && json.data?.task_url) {
                                        window.location.href = json.data.task_url;
                                    }
                                } finally {
                                    this.isProcessing = false;
                                }
                            },
                        }"
                        class="inline"
                    >
                        <button
                            type="button"
                            x-bind:disabled="isProcessing"
                            x-on:click="isOpen = true"
                            class="flex items-center gap-1 rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:border-blue-700/50 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            Convert to task
                        </button>

                        {{-- Confirmation modal --}}
                        <div
                            x-show="isOpen"
                            x-cloak
                            x-on:keydown.escape.window="isOpen = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="convert-dialog-title"
                        >
                            <div x-on:click="isOpen = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

                            <div
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                x-on:click.stop
                                class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900"
                            >
                                <h2 id="convert-dialog-title" class="text-base font-semibold text-gray-900 dark:text-white">
                                    Convert to task
                                </h2>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    This will mark the follow-up as done and create a linked task. All comments, links, and files will be transferred. Continue?
                                </p>
                                <div class="mt-6 flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        x-on:click="isOpen = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="doConvert()"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
                                    >
                                        Convert
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('follow-ups.destroy', $followUp->id) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            onclick="return confirm('Delete this follow-up?')"
                            class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                        >
                            Delete follow-up
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Activity feed sidebar --}}
        <div class="xl:col-span-1">
            <x-tl.activity-feed
                :parent="$followUp"
                parentType="follow-ups"
                :activities="$followUp->getActivityFeed()"
            />
        </div>
    </div>
@endsection
