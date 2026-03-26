@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    {{-- Header: title + type badge + status --}}
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex-1 min-w-0">
                {{-- Editable title --}}
                <div x-data="autoSaveField({ endpoint: '{{ route('meetings.update', $meeting->id) }}', field: 'title' })" x-init="value = @js($meeting->title)">
                    <label for="meeting-title" class="sr-only">Meeting title</label>
                    <input
                        id="meeting-title"
                        type="text"
                        x-model="value"
                        class="w-full border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-white"
                        placeholder="Meeting title"
                    >
                    <x-tl.auto-save-status />
                </div>

                {{-- Type + status + date --}}
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    @php
                        $statusBadge = match($meeting->status->value) {
                            'scheduled' => ['label' => 'Scheduled', 'class' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'],
                            'in_progress' => ['label' => 'In progress', 'class' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400'],
                            'completed' => ['label' => 'Completed', 'class' => 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400'],
                            'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'],
                            default => ['label' => $meeting->status->value, 'class' => 'bg-gray-100 text-gray-600'],
                        };
                    @endphp

                    <div
                        x-data="{
                            ...autoSaveField({ endpoint: '{{ route('meetings.update', $meeting->id) }}', field: 'type' }),
                            showOneOnOneWarning: false,
                            pendingValue: null,
                            attendeeCount: @js($meeting->attendees->count()),

                            handleTypeChange(newValue) {
                                if (newValue === 'one_on_one' && this.attendeeCount > 1) {
                                    this.pendingValue = newValue;
                                    this.showOneOnOneWarning = true;
                                    this.$nextTick(() => {
                                        this.$refs.typeSelect.value = this.value;
                                    });
                                    return;
                                }
                                this.value = newValue;
                                $dispatch('meeting-type-changed', { value: newValue });
                            },

                            confirmTypeChange() {
                                this.showOneOnOneWarning = false;
                                this.value = this.pendingValue;
                                this.pendingValue = null;
                                this.$nextTick(() => {
                                    this.$refs.typeSelect.value = this.value;
                                });
                                $dispatch('meeting-type-changed', { value: this.value });
                                $dispatch('meeting-clear-attendees');
                            },

                            cancelTypeChange() {
                                this.showOneOnOneWarning = false;
                                this.pendingValue = null;
                            },
                        }"
                        x-init="value = @js($meeting->type->value)"
                        x-on:meeting-attendee-count.window="attendeeCount = $event.detail.count"
                        class="inline-flex items-center"
                    >
                        <select
                            id="asf-type"
                            name="type"
                            x-ref="typeSelect"
                            x-bind:value="value"
                            x-on:change="handleTypeChange($event.target.value)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            @foreach($typeOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>

                        {{-- Warning modal: switching to 1-on-1 with multiple attendees --}}
                        <div
                            x-show="showOneOnOneWarning"
                            x-cloak
                            x-on:keydown.escape.window="cancelTypeChange()"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="type-change-dialog-title"
                        >
                            <div x-on:click="cancelTypeChange()" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

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
                                <h2 id="type-change-dialog-title" class="text-base font-semibold text-gray-900 dark:text-white">
                                    Switch to 1-on-1?
                                </h2>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    A 1-on-1 meeting can only have one attendee. All current attendees will be removed.
                                </p>
                                <div class="mt-6 flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        x-on:click="cancelTypeChange()"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="confirmTypeChange()"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                                    >
                                        Remove attendees & switch
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>

                    <span class="text-xs text-gray-400">&middot;</span>

                    <div
                        class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400"
                        x-data="Object.assign(autoSaveField({ endpoint: '{{ route('meetings.update', $meeting->id) }}', field: 'scheduled_at' }), datePicker())"
                        x-init="value = @js($meeting->scheduled_at->toDateString())"
                    >
                        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <input
                            type="text"
                            x-ref="input"
                            x-model="value"
                            placeholder="YYYY-MM-DD"
                            class="w-32 rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                    </div>
                </div>
            </div>

            {{-- Status transition controls --}}
            <div
                x-data="{
                    currentStatus: @js($meeting->status->value),
                    async transition(status) {
                        const response = await fetch('{{ route('meetings.transition', $meeting->id) }}', {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ status }),
                        });

                        if (response.ok) {
                            this.currentStatus = status;
                            window.location.reload();
                        }
                    },
                }"
                class="flex items-center gap-2"
            >
                <template x-if="currentStatus === 'scheduled'">
                    <button
                        type="button"
                        x-on:click="transition('in_progress')"
                        class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-600"
                    >Start meeting</button>
                </template>

                <template x-if="currentStatus === 'in_progress'">
                    <button
                        type="button"
                        x-on:click="transition('completed')"
                        class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-green-700"
                    >Complete</button>
                </template>

                <template x-if="currentStatus === 'scheduled' || currentStatus === 'in_progress'">
                    <button
                        type="button"
                        x-on:click="transition('cancelled')"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                    >Cancel</button>
                </template>
            </div>
        </div>

        {{-- Attendees --}}
        <div
            class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800"
            x-data="{
                attendees: @js($meeting->attendees->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'role' => $a->role])),
                allMembers: @js($memberOptions),
                allTeams: @js($teamOptions),
                meetingType: @js($meeting->type->value),
                showPicker: false,
                saving: false,

                get isOneOnOne() {
                    return this.meetingType === 'one_on_one';
                },

                get canAddMore() {
                    if (this.isOneOnOne) return this.attendees.length < 1;
                    return true;
                },

                get availableMembers() {
                    const currentIds = this.attendees.map(a => a.id);
                    return this.allMembers.filter(m => !currentIds.includes(m.value));
                },

                get availableTeams() {
                    return this.allTeams.filter(t => {
                        const teamMemberIds = this.allMembers.filter(m => m.team_id === t.value).map(m => m.value);
                        const currentIds = this.attendees.map(a => a.id);
                        return teamMemberIds.some(id => !currentIds.includes(id));
                    });
                },

                async syncAttendees() {
                    this.saving = true;
                    await fetch('{{ route('meetings.update', $meeting->id) }}', {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ attendee_ids: this.attendees.map(a => a.id) }),
                    });
                    this.saving = false;
                },

                addMember(memberId) {
                    const member = this.allMembers.find(m => m.value === Number(memberId));
                    if (member) {
                        this.attendees.push({ id: member.value, name: member.label, role: '' });
                        this.syncAttendees();
                    }
                    this.showPicker = false;
                },

                addTeam(teamId) {
                    const teamMembers = this.allMembers.filter(m => m.team_id === Number(teamId));
                    const currentIds = this.attendees.map(a => a.id);
                    let added = false;
                    teamMembers.forEach(m => {
                        if (!currentIds.includes(m.value)) {
                            this.attendees.push({ id: m.value, name: m.label, role: '' });
                            added = true;
                        }
                    });
                    if (added) this.syncAttendees();
                    this.showPicker = false;
                },

                removeMember(id) {
                    this.attendees = this.attendees.filter(a => a.id !== id);
                    this.syncAttendees();
                },
            }"
            x-on:meeting-type-changed.window="meetingType = $event.detail.value"
            x-on:meeting-clear-attendees.window="attendees = []; syncAttendees()"
            x-effect="$dispatch('meeting-attendee-count', { count: attendees.length })"
        >
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Attendees:</span>

            <template x-for="attendee in attendees" :key="attendee.id">
                <div class="flex items-center gap-1.5 rounded-full bg-gray-100 py-1 pl-3 pr-1 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <span x-text="attendee.name"></span>
                    <button
                        type="button"
                        x-on:click="removeMember(attendee.id)"
                        class="flex h-5 w-5 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="Remove attendee"
                    >
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </template>

            <div class="relative" x-show="canAddMore">
                <button
                    type="button"
                    x-on:click="showPicker = !showPicker"
                    class="flex h-7 w-7 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-400 transition hover:border-gray-400 hover:text-gray-600 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:text-gray-300"
                    aria-label="Add attendee"
                >
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </button>

                <div
                    x-show="showPicker"
                    x-cloak
                    x-on:click.outside="showPicker = false"
                    x-transition
                    class="absolute left-0 top-full z-10 mt-1 w-64 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                >
                    {{-- Team shortcut (team/other only) --}}
                    <template x-if="!isOneOnOne && availableTeams.length > 0">
                        <div class="border-b border-gray-100 pb-1 dark:border-gray-700">
                            <p class="px-3 py-1 text-xs font-medium text-gray-400">Add entire team</p>
                            <template x-for="team in availableTeams" :key="team.value">
                                <button
                                    type="button"
                                    x-on:click="addTeam(team.value)"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50"
                                >
                                    <svg class="h-3.5 w-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                                    </svg>
                                    <span x-text="team.label"></span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Individual members --}}
                    <template x-if="availableMembers.length === 0">
                        <p class="px-3 py-2 text-xs text-gray-400">No more members to add</p>
                    </template>
                    <template x-for="member in availableMembers" :key="member.value">
                        <button
                            type="button"
                            x-on:click="addMember(member.value)"
                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50"
                        >
                            <span x-text="member.label"></span>
                            <span class="ml-auto text-xs text-gray-400" x-text="member.team_name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <svg x-show="saving" x-cloak class="h-4 w-4 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        {{-- Linked calendar events --}}
        @if($meeting->calendarEventLinks->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Calendar events:</span>
                @foreach($meeting->calendarEventLinks as $link)
                    @if($link->calendarEvent)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $link->calendarEvent->subject }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Prev/next navigation --}}
        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
            @if($previousMeeting)
                <a
                    href="{{ route('meetings.show', $previousMeeting->id) }}"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                    Previous
                </a>
            @else
                <div></div>
            @endif

            @if($nextMeeting)
                <a
                    href="{{ route('meetings.show', $nextMeeting->id) }}"
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    Next
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            @else
                <div></div>
            @endif
        </div>
    </div>

    {{-- Tabbed content --}}
    <div
        x-data="{
            availableTabs: @js(array_values(array_filter([
                'prep',
                $recordingEnabled ? 'recording' : null,
                'transcription',
                $aiEnabled ? 'extractions' : null,
            ]))),
            activeTab: null,
            init() {
                const requested = new URLSearchParams(window.location.search).get('tab') || 'prep';
                this.activeTab = this.availableTabs.includes(requested) ? requested : 'prep';
            },
            setTab(tab) {
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.location.href = url.toString();
            },
        }"
        class="space-y-6"
    >
        {{-- Tab bar --}}
        <div
            class="flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900/50"
            role="tablist"
        >
            @foreach(array_filter([
                ['id' => 'prep',          'label' => 'Prep & Notes'],
                $recordingEnabled ? ['id' => 'recording', 'label' => 'Recording'] : null,
                ['id' => 'transcription', 'label' => 'Transcription'],
                $aiEnabled ? ['id' => 'extractions', 'label' => 'AI Extractions'] : null,
            ]) as $tab)
                <button
                    type="button"
                    role="tab"
                    x-on:click="setTab('{{ $tab['id'] }}')"
                    x-bind:aria-selected="activeTab === '{{ $tab['id'] }}'"
                    x-bind:class="activeTab === '{{ $tab['id'] }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Prep & Notes tab --}}
        <div
            x-show="activeTab === 'prep'"
            x-cloak
            role="tabpanel"
            aria-label="Prep & Notes"
        >
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2 space-y-6">
                {{-- Prep items --}}
                <div
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                    x-data="{
                        items: @js($meeting->prepItems->sortBy('sort_order')->values()->map(fn($item) => [
                            'id' => $item->id,
                            'content' => $item->content,
                            'type' => $item->type->value,
                            'duration_minutes' => $item->duration_minutes,
                            'is_discussed' => $item->is_discussed,
                            'team_member_name' => $item->teamMember?->name,
                        ])),
                        newType: 'agenda_item',
                        newDuration: '',
                        newAssignee: '',
                        newContent: '',
                        csrfToken: document.querySelector('meta[name=csrf-token]')?.content ?? '',

                        typeConfig: {
                            agenda_item: { icon: 'A', class: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' },
                            question: { icon: 'Q', class: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' },
                            action: { icon: '!', class: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' },
                        },

                        get totalMinutes() {
                            return this.items.reduce((sum, item) => sum + (item.duration_minutes || 0), 0);
                        },

                        async addPrepItem() {
                            const payload = {
                                meeting_id: {{ $meeting->id }},
                                content: this.newContent,
                                type: this.newType,
                            };
                            if (this.newDuration) payload.duration_minutes = parseInt(this.newDuration, 10);
                            if (this.newAssignee) payload.team_member_id = parseInt(this.newAssignee, 10);

                            const response = await fetch('{{ route('prep-items.store') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            });

                            if (response.ok) {
                                const json = await response.json();
                                this.items.push(json.data);
                                this.newContent = '';
                                this.newDuration = '';
                                this.newAssignee = '';
                            }
                        },

                        async toggleDiscussed(index) {
                            const item = this.items[index];
                            const newValue = !item.is_discussed;

                            item.is_discussed = newValue;

                            await fetch('/prep-items/' + item.id, {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ is_discussed: newValue }),
                            });
                        },

                        async deletePrepItem(index) {
                            const item = this.items[index];

                            const response = await fetch('/prep-items/' + item.id, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': this.csrfToken,
                                    'Accept': 'application/json',
                                },
                            });

                            if (response.ok) {
                                this.items.splice(index, 1);
                            }
                        },
                    }"
                >
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Prep items</h2>
                    </div>

                    {{-- Add new prep item form --}}
                    <form
                        class="border-b border-gray-100 px-5 py-3 dark:border-gray-800"
                        x-on:submit.prevent="addPrepItem()"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <label for="new-prep-item" class="sr-only">New prep item</label>
                                <input
                                    id="new-prep-item"
                                    type="text"
                                    x-model="newContent"
                                    placeholder="Add prep item…"
                                    required
                                    class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                                >
                                <button
                                    type="submit"
                                    class="flex shrink-0 items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                                >
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                    Add
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <select x-model="newType" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="agenda_item">Agenda</option>
                                    <option value="question">Question</option>
                                    <option value="action">Action</option>
                                </select>
                                <input
                                    type="number"
                                    x-model="newDuration"
                                    placeholder="Min"
                                    min="1"
                                    class="w-20 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                @if(count($attendeeOptions) > 0)
                                    <select x-model="newAssignee" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">Unassigned</option>
                                        @foreach($attendeeOptions as $opt)
                                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>
                    </form>

                    <x-tl.sortable-container
                        modelType="meeting_prep_item"
                        :endpoint="route('reorder')"
                        :containerId="'prep-items-' . $meeting->id"
                    >
                        <template x-for="(item, index) in items" x-bind:key="item.id">
                            <div
                                x-bind:data-id="item.id"
                                class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                                role="listitem"
                            >
                                <button
                                    type="button"
                                    class="drag-handle shrink-0 cursor-grab touch-none text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                    aria-label="Drag to reorder"
                                    tabindex="-1"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                        <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                    </svg>
                                </button>

                                <input
                                    type="checkbox"
                                    x-bind:checked="item.is_discussed"
                                    x-on:change="toggleDiscussed(index)"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                    x-bind:aria-label="item.content"
                                >

                                <span
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[0.625rem] font-bold"
                                    x-bind:class="(typeConfig[item.type] || { class: 'bg-gray-100 text-gray-600' }).class"
                                    x-text="(typeConfig[item.type] || { icon: '?' }).icon"
                                ></span>

                                <span
                                    class="flex-1 text-sm"
                                    x-bind:class="item.is_discussed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-white/90'"
                                    x-text="item.content"
                                ></span>

                                <template x-if="item.duration_minutes">
                                    <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" title="Estimated duration" x-text="item.duration_minutes + 'm'"></span>
                                </template>

                                <template x-if="item.team_member_name">
                                    <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="item.team_member_name"></span>
                                </template>

                                <button
                                    type="button"
                                    x-on:click="deletePrepItem(index)"
                                    class="shrink-0 rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                    aria-label="Remove prep item"
                                >
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                        </template>

                        <p
                            x-show="items.length === 0"
                            x-cloak
                            class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500"
                        >
                            No prep items yet.
                        </p>
                    </x-tl.sortable-container>

                    <div
                        x-show="totalMinutes > 0"
                        x-cloak
                        class="border-t border-gray-100 px-5 py-2 text-xs text-gray-400 dark:border-gray-800 dark:text-gray-500"
                    >
                        Total estimated time: <span x-text="totalMinutes"></span> min
                    </div>
                </div>

                {{-- Notes --}}
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Notes</h2>
                    </div>

                    <div
                        class="p-5"
                        x-data="Object.assign(
                            markdownEditor({ field: 'notes' }),
                            autoSaveField({ endpoint: '{{ route('meetings.update', $meeting->id) }}', field: 'notes' })
                        )"
                        x-init="content = @js($meeting->notes ?? ''); value = content;"
                    >
                        {{-- Editor/preview toggle --}}
                        <div class="mb-3 flex items-center gap-2">
                            <button
                                type="button"
                                x-on:click="togglePreview()"
                                x-bind:class="!isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                            >
                                Write
                            </button>
                            <button
                                type="button"
                                x-on:click="togglePreview()"
                                x-bind:class="isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                            >
                                Preview
                            </button>
                        </div>

                        <div x-show="!isPreview">
                            <label for="meeting-notes-editor" class="sr-only">Meeting notes</label>
                            <textarea
                                id="meeting-notes-editor"
                                name="notes"
                                x-model="content"
                                x-on:input="value = content"
                                rows="10"
                                placeholder="Write your notes in Markdown…"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                            ></textarea>
                        </div>

                        <div
                            x-show="isPreview"
                            x-cloak
                            x-html="preview"
                            class="prose prose-sm max-w-none min-h-32 text-gray-700 dark:prose-invert dark:text-gray-300"
                        ></div>

                        <x-tl.auto-save-status />
                    </div>
                </div>
                </div>

                {{-- Activity feed sidebar --}}
                <div>
                    <x-tl.activity-feed
                        :parent="$meeting"
                        parentType="meetings"
                        :activities="$meeting->getActivityFeed()"
                    />
                </div>
            </div>
        </div>

        {{-- Recording tab --}}
        @if($recordingEnabled)
        <div
            x-show="activeTab === 'recording'"
            x-cloak
            role="tabpanel"
            aria-label="Recording"
        >
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recording</h2>
                </div>

                <div class="p-5">
                    {{-- Existing recordings --}}
                    @foreach($meeting->recordings as $recording)
                        <div class="mb-4 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                            <audio controls preload="metadata" class="flex-1 h-10">
                                <source src="{{ route('api.meetings.recordings.stream', [$meeting->id, $recording->id]) }}" type="{{ $recording->mime_type }}">
                            </audio>

                            <div class="flex shrink-0 items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if($recording->duration_seconds)
                                    <span>{{ floor($recording->duration_seconds / 60) }}:{{ str_pad((string) ($recording->duration_seconds % 60), 2, '0', STR_PAD_LEFT) }}</span>
                                @endif
                                <span>{{ number_format($recording->size_bytes / 1024 / 1024, 1) }} MB</span>
                                <span>{{ $recording->created_at->format('d M H:i') }}</span>
                            </div>

                            <div
                                x-data="{
                                    async deleteRecording() {
                                        if (!confirm('Delete this recording?')) return;

                                        const response = await fetch('/api/v1/meetings/{{ $meeting->id }}/recordings/{{ $recording->id }}', {
                                            method: 'DELETE',
                                            headers: {
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                        });

                                        if (response.ok) window.location.reload();
                                    },
                                }"
                            >
                                @if($meeting->transcription?->status === 'completed')
                                    <p class="text-xs text-green-600 dark:text-green-400">Transcription available — audio can be safely deleted</p>
                                @endif

                                <button
                                    type="button"
                                    x-on:click="deleteRecording()"
                                    class="rounded p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                    aria-label="Delete recording"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach

                    {{-- Audio recorder --}}
                    @if(!$meeting->is_done)
                        <div
                            x-data="audioRecorder({
                                meetingId: {{ $meeting->id }},
                                uploadEndpoint: '/api/v1/meetings/{{ $meeting->id }}/recordings',
                                csrfToken: document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            })"
                        >
                            {{-- Idle state --}}
                            <template x-if="state === 'idle'">
                                <button
                                    type="button"
                                    x-on:click="startRecording()"
                                    class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="12" cy="12" r="6"/>
                                    </svg>
                                    Start recording
                                </button>
                            </template>

                            {{-- Recording / Paused state --}}
                            <template x-if="state === 'recording' || state === 'paused'">
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-3 w-3 rounded-full"
                                            :class="state === 'recording' ? 'bg-red-500 animate-pulse' : 'bg-amber-500'"
                                        ></span>
                                        <span class="font-mono text-lg font-semibold text-gray-900 dark:text-white" x-text="formattedTime"></span>
                                    </div>

                                    <template x-if="state === 'recording'">
                                        <button
                                            type="button"
                                            x-on:click="pauseRecording()"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                        >Pause</button>
                                    </template>

                                    <template x-if="state === 'paused'">
                                        <button
                                            type="button"
                                            x-on:click="resumeRecording()"
                                            class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/50 dark:bg-amber-500/10 dark:text-amber-400"
                                        >Resume</button>
                                    </template>

                                    <button
                                        type="button"
                                        x-on:click="stopRecording()"
                                        class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-900 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                    >Stop & save</button>
                                </div>
                            </template>

                            {{-- Uploading state --}}
                            <template x-if="state === 'uploading'">
                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Uploading recording…
                                </div>
                            </template>

                            {{-- Error state --}}
                            <template x-if="state === 'error'">
                                <div class="flex items-center gap-3">
                                    <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMessage"></p>
                                    <button
                                        type="button"
                                        x-on:click="state = 'idle'"
                                        class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                                    >Try again</button>
                                </div>
                            </template>

                            {{-- File upload fallback --}}
                            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <form
                                    x-on:submit.prevent="
                                        const formData = new FormData($event.target);
                                        state = 'uploading';
                                        const response = await fetch('/api/v1/meetings/{{ $meeting->id }}/recordings', {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                            body: formData,
                                        });

                                        if (response.ok) {
                                            window.location.reload();
                                        } else {
                                            const data = await response.json();
                                            state = 'error';
                                            errorMessage = data.message ?? 'Upload failed.';
                                        }
                                    "
                                >
                                    <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Or upload an audio file</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="file"
                                            name="audio"
                                            accept="audio/*"
                                            required
                                            class="text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-300"
                                        >
                                        <button
                                            type="submit"
                                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
                                        >Upload</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        @if($meeting->recordings->isEmpty())
                            <p class="text-sm text-gray-400 dark:text-gray-500">No recordings for this meeting.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @endif

        {{-- Transcription tab --}}
        <div
            x-show="activeTab === 'transcription'"
            x-cloak
            role="tabpanel"
            aria-label="Transcription"
        >
            <div
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                x-data="transcriptionViewer({
                    meetingId: {{ $meeting->id }},
                    csrfToken: @js(csrf_token()),
                    status: @js($meeting->transcription?->status?->value),
                    content: @js($meeting->transcription?->content ?? ''),
                    errorMessage: @js($meeting->transcription?->error_message ?? ''),
                    diarizationStatus: @js($meeting->transcription?->diarization_status?->value),
                    diarizedContent: @js($meeting->transcription?->diarized_content ?? ''),
                    diarizationError: @js($meeting->transcription?->diarization_error ?? ''),
                    diarizationEnabled: @js(config('meetings.diarization.enabled')),
                    processingStartedAt: @js($meeting->transcription?->processing_started_at?->toIso8601String()),
                    estimatedDurationSeconds: @js($estimatedTranscriptionSeconds),
                    diarizationStartedAt: @js($meeting->transcription?->diarization_started_at?->toIso8601String()),
                    estimatedDiarizationSeconds: @js($estimatedDiarizationSeconds),
                    transcriptionEnabled: @js($transcriptionEnabled),
                    canChooseMode: @js($serverSpeechEnabled && config('meetings.diarization.enabled')),
                    hasRecordings: @js($meeting->recordings->count() > 0),
                    provider: @js($meeting->transcription?->provider),
                    speechServiceMode: @js($speechServiceMode),
                    speechServiceUrl: @js($speechServiceUrl),
                    speechServiceToken: @js($speechServiceToken),
                    recordingStreamUrl: @js($recordingStreamUrl),
                    transcriptionLanguage: @js($meeting->transcription_language),
                    autoStart: @js((bool) config('meetings.transcription.auto_start', true)),
                })"

            >
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Transcription</h2>
                    <div class="flex items-center gap-2">
                        <template x-if="canChooseMode && hasRecordings && !isProcessing">
                            <div class="flex items-center rounded-lg border border-gray-200 dark:border-gray-700">
                                <button
                                    type="button"
                                    x-on:click="processingMode = 'diarize'"
                                    class="rounded-l-lg px-2.5 py-1 text-xs font-medium transition"
                                    :class="processingMode === 'diarize'
                                        ? 'bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400'
                                        : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'"
                                >With speakers</button>
                                <button
                                    type="button"
                                    x-on:click="processingMode = 'transcribe'"
                                    class="rounded-r-lg border-l border-gray-200 px-2.5 py-1 text-xs font-medium transition dark:border-gray-700"
                                    :class="processingMode === 'transcribe'
                                        ? 'bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400'
                                        : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'"
                                >Text only</button>
                            </div>
                        </template>
                        <template x-if="transcriptionEnabled && hasRecordings && status === 'failed'">
                            <button
                                type="button"
                                x-on:click="retry()"
                                class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/50 dark:bg-amber-500/10 dark:text-amber-400"
                            >Retry</button>
                        </template>
                        <template x-if="transcriptionEnabled && hasRecordings && (status === 'completed' || status === 'failed')">
                            <button
                                type="button"
                                x-on:click="status === 'completed' ? showRetranscribeModal = true : retranscribeAll()"
                                class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                            >Retranscribe all</button>
                        </template>
                        <template x-if="isManual && status === 'completed'">
                            <button
                                type="button"
                                x-on:click="showDeleteTranscriptionModal = true"
                                class="rounded-lg border border-red-300 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:border-red-700/50 dark:text-red-400 dark:hover:bg-red-500/10"
                            >Delete transcription</button>
                        </template>
                        <template x-if="!isManual || !status || status === 'failed'">
                            <button
                                type="button"
                                x-on:click="showManualInput ? showManualInput = false : (status === 'completed' && !isManual ? showManualConfirmModal = true : showManualInput = true)"
                                class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                x-text="showManualInput ? 'Cancel' : 'Manual input'"
                            ></button>
                        </template>
                    </div>
                </div>

                <div class="p-5">
                    {{-- Recording cleanup prompt --}}
                    {{-- Recording cleanup prompt --}}
                    <template x-if="showDeletePrompt">
                        <div class="mb-4 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-800/50 dark:bg-green-900/20">
                            <p class="text-sm text-green-700 dark:text-green-400">
                                Transcription complete. Delete the recording to free up storage?
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    x-on:click="showDeletePrompt = false"
                                    class="rounded px-2.5 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >Keep</button>
                                <button
                                    type="button"
                                    x-on:click="showDeleteModal = true"
                                    class="rounded bg-red-500 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-red-600"
                                >Delete recording</button>
                            </div>
                        </div>
                    </template>

                    {{-- Delete recording confirmation modal --}}
                    <div
                        x-show="showDeleteModal"
                        x-cloak
                        x-on:keydown.escape.window="showDeleteModal = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-recording-dialog-title"
                    >
                        <div
                            x-on:click="showDeleteModal = false"
                            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
                        ></div>

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
                            <h2
                                id="delete-recording-dialog-title"
                                class="text-base font-semibold text-gray-900 dark:text-white"
                            >Delete recording?</h2>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                The recording will be permanently deleted. The transcription will be kept. This action cannot be undone.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="showDeleteModal = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >Cancel</button>

                                <button
                                    type="button"
                                    x-on:click="showDeleteModal = false; deleteRecordings()"
                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                                >Delete</button>
                            </div>
                        </div>
                    </div>

                    {{-- Manual input confirmation modal --}}
                    <div
                        x-show="showManualConfirmModal"
                        x-cloak
                        x-on:keydown.escape.window="showManualConfirmModal = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="manual-input-dialog-title"
                    >
                        <div
                            x-on:click="showManualConfirmModal = false"
                            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
                        ></div>

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
                            <h2
                                id="manual-input-dialog-title"
                                class="text-base font-semibold text-gray-900 dark:text-white"
                            >Replace transcription?</h2>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Saving manual input will replace the current transcription and remove any speaker diarization data. This cannot be undone.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="showManualConfirmModal = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >Cancel</button>

                                <button
                                    type="button"
                                    x-on:click="showManualConfirmModal = false; showManualInput = true"
                                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700 dark:hover:bg-amber-500"
                                >Continue</button>
                            </div>
                        </div>
                    </div>

                    {{-- Delete transcription confirmation modal --}}
                    <div
                        x-show="showDeleteTranscriptionModal"
                        x-cloak
                        x-on:keydown.escape.window="showDeleteTranscriptionModal = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-transcription-dialog-title"
                    >
                        <div
                            x-on:click="showDeleteTranscriptionModal = false"
                            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
                        ></div>

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
                            <h2
                                id="delete-transcription-dialog-title"
                                class="text-base font-semibold text-gray-900 dark:text-white"
                            >Delete transcription?</h2>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                The transcription will be permanently deleted. This action cannot be undone.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="showDeleteTranscriptionModal = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >Cancel</button>

                                <button
                                    type="button"
                                    x-on:click="showDeleteTranscriptionModal = false; deleteTranscription()"
                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                                >Delete</button>
                            </div>
                        </div>
                    </div>

                    {{-- Retranscribe confirmation modal --}}
                    <div
                        x-show="showRetranscribeModal"
                        x-cloak
                        x-on:keydown.escape.window="showRetranscribeModal = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="retranscribe-dialog-title"
                    >
                        <div
                            x-on:click="showRetranscribeModal = false"
                            class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
                        ></div>

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
                            <h2
                                id="retranscribe-dialog-title"
                                class="text-base font-semibold text-gray-900 dark:text-white"
                            >Retranscribe?</h2>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                This will replace the current transcription with a new one from the recording. Any existing speaker diarization data will also be replaced.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="showRetranscribeModal = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >Cancel</button>

                                <button
                                    type="button"
                                    x-on:click="showRetranscribeModal = false; retranscribeAll()"
                                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700 dark:hover:bg-amber-500"
                                >Retranscribe</button>
                            </div>
                        </div>
                    </div>

                    {{-- Local processing blocking modal --}}
                    <div
                        x-show="localProcessing"
                        x-cloak
                        x-on:keydown.tab.prevent
                        x-effect="if (localProcessing) { $nextTick(() => $refs.localProcessingCard?.focus()); window._anvilBeforeUnload = (e) => { e.preventDefault(); }; window.addEventListener('beforeunload', window._anvilBeforeUnload); } else if (window._anvilBeforeUnload) { window.removeEventListener('beforeunload', window._anvilBeforeUnload); window._anvilBeforeUnload = null; }"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="local-processing-dialog-title"
                        aria-describedby="local-processing-dialog-warning"
                    >
                        <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>

                        <div
                            x-ref="localProcessingCard"
                            tabindex="-1"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div class="flex items-start gap-4">
                                <div class="shrink-0 mt-0.5">
                                    <svg class="h-6 w-6 animate-spin text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h2
                                        id="local-processing-dialog-title"
                                        class="text-base font-semibold text-gray-900 dark:text-white"
                                        x-text="currentPhaseLabel"
                                    ></h2>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                                        Elapsed: <span class="font-medium" x-text="formatDuration(localElapsedSeconds)"></span>
                                    </p>

                                    <p id="local-processing-dialog-warning" class="mt-3 text-sm text-amber-700 dark:text-amber-400">
                                        Please keep this page open. Closing or navigating away will interrupt the transcription.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Unified processing progress --}}
                    <template x-if="isProcessing">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="h-4 w-4 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>
                                    <span x-show="totalPhases > 1" class="font-medium" x-text="'Step ' + currentPhase + '/' + totalPhases + ': '"></span>
                                    <span x-text="currentPhaseLabel"></span>
                                </span>
                            </div>

                            {{-- Overall progress bar (weighted by estimates) --}}
                            <template x-if="overallProgressPercent !== null">
                                <div class="space-y-2">
                                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            class="h-2 rounded-full bg-brand-500 transition-all duration-1000"
                                            :style="'width: ' + overallProgressPercent + '%'"
                                        ></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500">
                                        <span x-text="'Elapsed: ' + formatDuration(currentElapsedSeconds)"></span>
                                        <span x-text="totalRemainingSeconds > 0
                                            ? 'Est. remaining: ~' + formatDuration(totalRemainingSeconds)
                                            : 'Taking longer than expected…'"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Fallback: show elapsed only when no estimate available --}}
                            <template x-if="overallProgressPercent === null && (currentStartedAt || localProcessing)">
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    <span x-text="'Elapsed: ' + formatDuration(currentElapsedSeconds)"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Failed --}}
                    <template x-if="status === 'failed'">
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <p class="font-medium">Transcription failed</p>
                            <p class="mt-1 text-xs" x-text="errorMessage"></p>
                        </div>
                    </template>

                    {{-- Diarization failed (show plain text with warning) --}}
                    <template x-if="status === 'completed' && diarizationStatus === 'failed' && !hasDiarization && !showManualInput">
                        <div>
                            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                                Speaker identification failed — showing plain transcription.
                            </div>
                            <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 whitespace-pre-line dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" x-text="content"></div>
                        </div>
                    </template>

                    {{-- Completed with diarization — chat view --}}
                    <template x-if="status === 'completed' && hasDiarization && !showManualInput">
                        <div class="max-h-[32rem] overflow-y-auto space-y-3">
                            <template x-for="(segment, i) in segments" x-bind:key="i">
                                <div class="flex gap-3">
                                    <div class="shrink-0 pt-0.5">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-[0.625rem] font-semibold"
                                            x-bind:class="speakerColor(segment.speaker)"
                                            x-text="speakerLabel(segment.speaker)"
                                        ></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300" x-text="segment.text"></p>
                                        <p class="mt-0.5 text-[0.625rem] text-gray-400 dark:text-gray-600" x-text="formatTime(segment.start) + ' – ' + formatTime(segment.end)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Completed without diarization — plain text --}}
                    <template x-if="status === 'completed' && !hasDiarization && !isDiarizing && diarizationStatus !== 'failed' && !showManualInput">
                        <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 whitespace-pre-line dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" x-text="content"></div>
                    </template>

                    {{-- Local processing error --}}
                    <template x-if="localProcessingError && !localProcessing">
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <p class="font-medium">Local processing failed</p>
                            <p class="mt-1 text-xs" x-text="localProcessingError"></p>
                        </div>
                    </template>

                    {{-- No transcription yet --}}
                    <template x-if="!status && !showManualInput && !isProcessing && !localProcessingError">
                        <div class="space-y-3">
                            @if($transcriptionEnabled && $meeting->recordings->count() > 0)
                                <p class="text-sm text-gray-400 dark:text-gray-500">Recording available. Start transcription or enter it manually.</p>
                                <x-ui.button size="sm" x-on:click="retranscribeAll()">Start transcription</x-ui.button>
                            @elseif(!$transcriptionEnabled)
                                <p class="text-sm text-gray-400 dark:text-gray-500">Use the manual input button to paste a transcription.</p>
                            @else
                                <p class="text-sm text-gray-400 dark:text-gray-500">No transcription available yet. Record or upload audio to start.</p>
                            @endif
                        </div>
                    </template>

                    {{-- Manual input --}}
                    <template x-if="showManualInput">
                        <div>
                            <label for="manual-transcription" class="sr-only">Manual transcription</label>
                            <textarea
                                id="manual-transcription"
                                x-model="manualContent"
                                rows="10"
                                placeholder="Paste or type the transcription here…"
                                class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                            ></textarea>
                            <button
                                type="button"
                                x-on:click="saveManual()"
                                class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500"
                            >Save transcription</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- AI Extractions tab --}}
        @if($aiEnabled)
        <div
            x-show="activeTab === 'extractions'"
            x-cloak
            role="tabpanel"
            aria-label="AI Extractions"
        >
                <div
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                    x-data="extractionReview({
                        meetingId: {{ $meeting->id }},
                        initialExtractions: @js($meeting->extractions->map(fn ($e) => [
                            'id' => $e->id,
                            'type' => $e->type->value,
                            'content' => $e->content,
                            'assignee_id' => $e->assignee_id,
                            'assignee' => $e->assignee ? ['id' => $e->assignee->id, 'name' => $e->assignee->name] : null,
                            'priority' => $e->priority,
                            'deadline' => $e->deadline?->toDateString(),
                            'status' => $e->status->value,
                        ])->values()),
                        hasTranscription: @js($meeting->transcription !== null && $meeting->transcription->status?->value === 'completed'),
                        summary: @js($meeting->summary ?? ''),
                        csrfToken: document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        teamOptions: @js($teamOptions),
                        memberOptions: @js($memberOptions),
                    })"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                            AI Extractions
                            <template x-if="pendingExtractions.length > 0">
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" x-text="pendingExtractions.length"></span>
                            </template>
                        </h2>
                        <div class="flex items-center gap-2">
                            <template x-if="selectedIds.length > 0">
                                <div class="flex items-center gap-1">
                                    <button type="button" x-on:click="bulkAccept()" class="rounded-lg bg-green-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-green-700" :disabled="loading">Accept selected</button>
                                    <button type="button" x-on:click="bulkReject()" class="rounded-lg border border-red-300 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:border-red-700/50 dark:text-red-400" :disabled="loading">Reject selected</button>
                                </div>
                            </template>
                            <template x-if="pendingExtractions.length > 0">
                                <button type="button" x-on:click="selectedIds.length === pendingExtractions.length ? deselectAll() : selectAll()" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400" x-text="selectedIds.length === pendingExtractions.length ? 'Deselect all' : 'Select all'"></button>
                            </template>
                            <template x-if="hasTranscription && !extracting">
                                <button type="button" x-on:click="showReExtractConfirm = true" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400" :disabled="loading" x-text="extractions.length > 0 ? 'Re-extract' : 'Extract'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Summary --}}
                    <template x-if="summary">
                        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Summary</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300" x-text="summary"></p>
                        </div>
                    </template>

                    {{-- Extraction items --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="extraction in extractions" :key="extraction.id">
                            <div class="px-5 py-3">
                                {{-- View mode --}}
                                <template x-if="editingId !== extraction.id">
                                    <div class="flex items-start gap-3">
                                        <template x-if="extraction.status === 'pending'">
                                            <input type="checkbox" :checked="selectedIds.includes(extraction.id)" x-on:change="toggleSelection(extraction.id)" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 dark:border-gray-600 dark:bg-gray-800">
                                        </template>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="{
                                                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': extraction.type === 'task',
                                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': extraction.type === 'follow_up',
                                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': extraction.type === 'agreement',
                                                        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400': extraction.type === 'decision',
                                                    }"
                                                    x-text="extraction.type.replace('_', ' ')"
                                                ></span>

                                                <span class="text-sm text-gray-800 dark:text-white/90" :class="{ 'line-through text-gray-400': extraction.status === 'rejected' }" x-text="extraction.content"></span>
                                            </div>

                                            <div class="mt-1 flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                                                <template x-if="extraction.assignee">
                                                    <span x-text="extraction.assignee.name"></span>
                                                </template>
                                                <template x-if="extraction.priority">
                                                    <span x-text="extraction.priority"></span>
                                                </template>
                                                <template x-if="extraction.deadline">
                                                    <span x-text="extraction.deadline"></span>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-1">
                                            <template x-if="extraction.status === 'pending'">
                                                <div class="flex items-center gap-1">
                                                    <button type="button" x-on:click="accept(extraction)" class="rounded px-2 py-1 text-xs font-medium text-green-600 transition hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-500/10" :disabled="loading">Accept</button>
                                                    <button type="button" x-on:click="startEdit(extraction)" class="rounded px-2 py-1 text-xs font-medium text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10" :disabled="loading">Edit</button>
                                                    <button type="button" x-on:click="reject(extraction)" class="rounded px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10" :disabled="loading">Reject</button>
                                                </div>
                                            </template>
                                            <template x-if="extraction.status !== 'pending'">
                                                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="{
                                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': extraction.status === 'accepted' || extraction.status === 'modified',
                                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': extraction.status === 'rejected',
                                                    }"
                                                    x-text="extraction.status"
                                                ></span>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Edit mode is now handled by the edit modal below --}}
                            </div>
                        </template>
                    </div>

                    <template x-if="extracting">
                        <div class="px-5 py-8 text-center">
                            <svg class="mx-auto h-6 w-6 animate-spin text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-400">Analyzing transcription with AI…</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">This usually takes 10–30 seconds.</p>
                        </div>
                    </template>

                    <template x-if="!extracting && extractions.length === 0 && hasTranscription">
                        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">No extractions yet. Use the Extract button to extract insights.</p>
                    </template>

                    <template x-if="!extracting && extractions.length === 0 && !hasTranscription">
                        <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">No extractions available. Complete a transcription first.</p>
                    </template>

                    {{-- Re-extract confirmation modal --}}
                    <div
                        x-show="showReExtractConfirm"
                        x-cloak
                        x-on:keydown.escape.window="showReExtractConfirm = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="re-extract-dialog-title"
                    >
                        <div x-on:click="showReExtractConfirm = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

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
                            <h2 id="re-extract-dialog-title" class="text-base font-semibold text-gray-900 dark:text-white">
                                <span x-text="extractions.length > 0 ? 'Re-extract insights' : 'Extract insights'"></span>
                            </h2>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                This will remove all pending extractions and re-analyze the transcription. This action cannot be undone.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="showReExtractConfirm = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    x-on:click="reExtract()"
                                    :disabled="loading"
                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                                >
                                    Confirm
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Edit extraction modal --}}
                    <div
                        x-show="editingId !== null"
                        x-cloak
                        x-on:keydown.escape.window="cancelEdit()"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="edit-extraction-dialog-title"
                    >
                        <div x-on:click="cancelEdit()" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

                        <div
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            x-on:click.stop
                            class="relative w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900"
                        >
                            <h2 id="edit-extraction-dialog-title" class="text-base font-semibold text-gray-900 dark:text-white">
                                Edit extraction
                            </h2>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label for="edit-extraction-content" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Content</label>
                                    <input
                                        id="edit-extraction-content"
                                        type="text"
                                        x-model="editContent"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                    >
                                </div>

                                <div>
                                    <label for="edit-extraction-type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <select
                                        id="edit-extraction-type"
                                        x-model="editType"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                    >
                                        <option value="task">Task</option>
                                        <option value="follow_up">Follow-up</option>
                                        <option value="agreement">Agreement</option>
                                        <option value="decision">Decision</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="edit-extraction-team" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                                        <select
                                            id="edit-extraction-team"
                                            x-model="editTeamId"
                                            x-on:change="editAssigneeId = ''"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                        >
                                            <option value="">All teams</option>
                                            <template x-for="team in teamOptions" :key="team.value">
                                                <option :value="team.value" x-text="team.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="edit-extraction-assignee" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Assigned to</label>
                                        <select
                                            id="edit-extraction-assignee"
                                            x-model="editAssigneeId"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                        >
                                            <option value="">Unassigned</option>
                                            <template x-for="member in filteredMemberOptions" :key="member.value">
                                                <option :value="member.value" x-text="member.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="edit-extraction-priority" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                                        <select
                                            id="edit-extraction-priority"
                                            x-model="editPriority"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                        >
                                            <option value="">No priority</option>
                                            <option value="urgent">Urgent</option>
                                            <option value="high">High</option>
                                            <option value="normal">Normal</option>
                                            <option value="low">Low</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="edit-extraction-deadline" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Deadline</label>
                                        <input
                                            id="edit-extraction-deadline"
                                            type="date"
                                            x-model="editDeadline"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    x-on:click="cancelEdit()"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    x-on:click="acceptWithEdits(extractions.find(e => e.id === editingId))"
                                    :disabled="loading"
                                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700 dark:hover:bg-green-500"
                                >
                                    Save & accept
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        @endif

    </div>

    {{-- Actions --}}
    <div class="mt-6 flex flex-wrap items-center gap-3">
        <a
            href="{{ route('meetings.index') }}"
            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            &larr; Back to meetings
        </a>

        <div class="ml-auto flex items-center gap-2">
            @if(!$meeting->is_done)
                <form method="POST" action="{{ route('meetings.done', $meeting->id) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="rounded-lg border border-green-300 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-100 dark:border-green-700/50 dark:bg-green-500/10 dark:text-green-400 dark:hover:bg-green-500/20"
                    >
                        Mark as done
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('meetings.undone', $meeting->id) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/50 dark:bg-amber-500/10 dark:text-amber-400 dark:hover:bg-amber-500/20"
                    >
                        Undo done
                    </button>
                </form>
            @endif

            <x-tl.confirm-dialog-modal
                trigger-id="delete-meeting-{{ $meeting->id }}"
                title="Delete meeting?"
                message="This meeting and all its data (recordings, transcriptions, extractions) will be permanently deleted. This action cannot be undone."
            >
                <x-slot:trigger>
                    <button
                        type="button"
                        class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                    >
                        Delete meeting
                    </button>
                </x-slot:trigger>

                <x-slot:form>
                    <form id="confirm-form-delete-meeting-{{ $meeting->id }}" method="POST" action="{{ route('meetings.destroy', $meeting->id) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                </x-slot:form>
            </x-tl.confirm-dialog-modal>
        </div>
    </div>
@endsection
