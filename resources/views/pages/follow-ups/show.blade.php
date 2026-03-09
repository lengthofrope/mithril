@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    @php
        $endpoint = '/api/v1/follow-ups/' . $followUp->id;
    @endphp

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
    </div>

    {{-- Actions --}}
    <div class="mt-4 flex items-center gap-3">
        <a
            href="{{ route('follow-ups.index') }}"
            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            &larr; Back to follow-ups
        </a>

        <div class="ml-auto">
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
@endsection
