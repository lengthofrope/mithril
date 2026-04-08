{{-- Schedule meeting modal --}}
<div
    x-data="createModal({ memberOptions: @js($memberOptions), teamOptions: @js($teamOptions) })"
    data-create-modal="meeting"
    @create-entity.window="if ($event.detail.type === 'meeting') addOpen = true"
    x-effect="if (selectedType === 'one_on_one') { selectedTeamIds = []; }"
>
    <button
        type="button"
        x-on:click="addOpen = !addOpen"
        class="flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Schedule meeting
    </button>

    <div
        x-show="addOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        x-on:keydown.escape.window="addOpen = false"
    >
        <div
            class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            x-on:click.outside="addOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Schedule a new meeting</h2>
            <form method="POST" action="{{ route('meetings.store') }}">
                @csrf

                {{-- Title --}}
                <div class="mb-4">
                    <label for="new-meeting-title" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Title <span class="text-red-500">*</span></label>
                    <input
                        id="new-meeting-title"
                        type="text"
                        name="title"
                        required
                        placeholder="Meeting title"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                    >
                </div>

                <div class="mb-4 grid grid-cols-2 gap-3">
                    {{-- Type --}}
                    <div>
                        <label for="new-meeting-type" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Type <span class="text-red-500">*</span></label>
                        <select
                            id="new-meeting-type"
                            name="type"
                            x-model="selectedType"
                            required
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="one_on_one">1-on-1</option>
                            <option value="team">Team</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {{-- Date --}}
                    <div x-data="datePicker()">
                        <label for="new-meeting-date" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Date <span class="text-red-500">*</span></label>
                        <input
                            id="new-meeting-date"
                            type="text"
                            name="scheduled_at"
                            x-ref="input"
                            required
                            value="{{ now()->addDays(7)->toDateString() }}"
                            placeholder="YYYY-MM-DD"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                    </div>
                </div>

                {{-- 1-on-1: single attendee picker with optional team filter --}}
                <div x-show="isOneOnOne" class="mb-4 grid grid-cols-2 gap-3">
                    <div>
                        <label for="new-meeting-team-filter" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Filter by team</label>
                        <select
                            id="new-meeting-team-filter"
                            x-model="selectedTeamId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">All teams</option>
                            @foreach($teamOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="new-meeting-member" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Attendee</label>
                        <select
                            id="new-meeting-member"
                            name="attendee_ids[]"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">No attendee</option>
                            <template x-for="member in filteredMembers" :key="member.value">
                                <option :value="member.value" x-text="member.label"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Team/Other: team picker to add whole teams --}}
                <div x-show="!isOneOnOne" class="mb-4">
                    <label for="new-meeting-add-team" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Add teams</label>
                    <div class="flex items-center gap-2">
                        <select
                            id="new-meeting-add-team"
                            x-on:change="addTeam($event.target.value); $event.target.value = ''"
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            <option value="">Select a team…</option>
                            <template x-for="team in availableTeams" :key="team.value">
                                <option :value="team.value" x-text="team.label"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Selected teams chips --}}
                    <div class="mt-2 flex flex-wrap gap-2" x-show="selectedTeamIds.length > 0">
                        <template x-for="teamId in selectedTeamIds" :key="teamId">
                            <div class="flex items-center gap-1 rounded-full bg-gray-100 py-1 pl-3 pr-1 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                <span x-text="teamLabel(teamId)"></span>
                                <input type="hidden" name="team_ids[]" :value="teamId">
                                <button
                                    type="button"
                                    x-on:click="removeTeam(teamId)"
                                    class="flex h-5 w-5 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                    aria-label="Remove team"
                                >
                                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500"
                    >
                        Schedule
                    </button>
                    <button
                        type="button"
                        x-on:click="addOpen = false"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
