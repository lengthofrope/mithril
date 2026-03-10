@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    {{-- Member header --}}
    <div class="mb-6 flex flex-wrap items-center gap-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @php
            $statusColorMap = [
                'available'           => 'bg-green-500',
                'absent'              => 'bg-red-400',
                'partially_available' => 'bg-yellow-500',
            ];
            $statusLabelMap = [
                'available'           => 'Available',
                'absent'              => 'Absent',
                'partially_available' => 'Partially available',
            ];
            $statusKey = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
            $statusColor = $statusColorMap[$statusKey] ?? 'bg-gray-400';
            $statusLabel = $statusLabelMap[$statusKey] ?? ucfirst($statusKey);
        @endphp

        <div class="relative shrink-0" x-data="{ showAvatarMenu: false }">
            <button
                type="button"
                class="group relative rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                x-on:click="showAvatarMenu = !showAvatarMenu"
                title="Change profile photo"
            >
                <x-tl.team-member-avatar :member="$member" size="xl" />
                <span class="absolute inset-0 flex items-center justify-center rounded-full bg-black/0 transition group-hover:bg-black/40">
                    <svg class="h-5 w-5 text-white opacity-0 transition group-hover:opacity-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                    </svg>
                </span>
            </button>
            <span
                class="absolute bottom-0 right-0 h-4 w-4 rounded-full border-2 border-white dark:border-gray-900 {{ $statusColor }}"
                title="{{ $statusLabel }}"
            ></span>

            {{-- Avatar upload/remove dropdown --}}
            <div
                x-show="showAvatarMenu"
                x-cloak
                x-on:click.outside="showAvatarMenu = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute left-0 top-full z-10 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            >
                <form
                    method="POST"
                    action="{{ route('members.avatar.upload', $member) }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    <label
                        for="member-avatar-upload"
                        class="flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Upload photo
                    </label>
                    <input
                        id="member-avatar-upload"
                        type="file"
                        name="avatar"
                        accept="image/*"
                        required
                        class="sr-only"
                        x-data
                        x-on:change="$el.closest('form').submit()"
                    >
                </form>

                @if($member->avatar_path)
                    <form method="POST" action="{{ route('members.avatar.delete', $member) }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            Remove photo
                        </button>
                    </form>
                @endif

                <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-400 dark:border-gray-800 dark:text-gray-500">
                    JPG, PNG or GIF. Max 2MB.
                </p>
            </div>

            @error('avatar')
                <p class="absolute left-0 top-full mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $member->name }}
            </h1>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ $member->role }}
                @if($member->team)
                    &middot;
                    <a href="{{ route('teams.show', $member->team->id) }}" class="text-blue-600 hover:underline dark:text-blue-400">
                        {{ $member->team->name }}
                    </a>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ $statusLabel }}
            </p>

            @if($member->status_source?->value === 'microsoft')
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true"></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Auto-synced via Office 365
                        @if($member->status_synced_at)
                            &middot; {{ $member->status_synced_at->diffForHumans() }}
                        @endif
                    </span>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2" x-data="{ deleteOpen: false }">
            <a
                href="{{ route('bilas.index', ['team_member_id' => $member->id]) }}"
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Bilas
            </a>

            <button
                type="button"
                x-on:click="deleteOpen = true"
                class="flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-500/10"
                title="Remove member"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Remove
            </button>

            {{-- Delete member confirmation modal --}}
            <div
                x-show="deleteOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                x-on:keydown.escape.window="deleteOpen = false"
            >
                <div
                    class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    x-on:click.outside="deleteOpen = false"
                >
                    <h2 class="mb-2 text-base font-semibold text-gray-900 dark:text-white">Remove member</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to remove <strong>{{ $member->name }}</strong> from the team?
                    </p>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('teams.member.destroy', $member->id) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </form>
                        <button
                            type="button"
                            x-on:click="deleteOpen = false"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Notes auto-save --}}
    <div class="mb-6">
        <x-tl.auto-save-field
            :endpoint="route('members.update', $member->id)"
            field="notes"
            :value="$member->notes ?? ''"
            type="textarea"
            label="Notes about {{ $member->name }}"
        />
    </div>

    {{-- Microsoft email auto-save --}}
    <div class="mb-6">
        <x-tl.auto-save-field
            :endpoint="route('members.update', $member->id)"
            field="microsoft_email"
            :value="$member->microsoft_email ?? ''"
            type="email"
            label="Microsoft email (for availability sync)"
        />
    </div>

    {{-- Status sync settings --}}
    <div class="mb-6 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Status sync</h2>
        </div>
        <div class="p-5" x-data="{ statusSource: '{{ $member->status_source?->value ?? 'manual' }}' }">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label for="status-source" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Status source
                    </label>
                    <select
                        id="status-source"
                        x-model="statusSource"
                        x-on:change="
                            fetch('{{ route('members.update', $member->id) }}', {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ status_source: statusSource })
                            })
                        "
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                        <option value="manual">Manual</option>
                        <option value="microsoft">Auto (Office 365)</option>
                    </select>
                </div>

                {{-- Note: this warning is server-rendered — it reflects the email value at page load only --}}
                <div x-show="statusSource === 'microsoft'" x-cloak class="text-xs text-gray-500 dark:text-gray-400">
                    <p>Status will be automatically synced from the Microsoft email address above.</p>
                    @if(!$member->microsoft_email)
                        <p class="mt-1 text-amber-600 dark:text-amber-400">
                            Microsoft email is required for auto-sync.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sections --}}
    <div
        x-data="{ activeTab: 'tasks' }"
        class="space-y-6"
    >
        {{-- Tabs --}}
        <div
            class="flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900/50"
            role="tablist"
        >
            @foreach([
                ['id' => 'tasks',      'label' => 'Tasks'],
                ['id' => 'followups',  'label' => 'Follow-ups'],
                ['id' => 'bilas',      'label' => 'Bila history'],
                ['id' => 'agreements', 'label' => 'Agreements'],
                ['id' => 'notes',      'label' => 'Notes'],
            ] as $tab)
                <button
                    type="button"
                    role="tab"
                    x-on:click="activeTab = '{{ $tab['id'] }}'"
                    x-bind:aria-selected="activeTab === '{{ $tab['id'] }}'"
                    x-bind:class="activeTab === '{{ $tab['id'] }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Tasks tab --}}
        <div
            x-show="activeTab === 'tasks'"
            x-cloak
            role="tabpanel"
            aria-label="Tasks"
        >
            <div class="space-y-3" role="list">
                @forelse($memberTasks as $task)
                    <x-tl.task-card :task="$task" />
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No open tasks for this member.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Follow-ups tab --}}
        <div
            x-show="activeTab === 'followups'"
            x-cloak
            role="tabpanel"
            aria-label="Follow-ups"
        >
            <div class="space-y-3" role="list">
                @forelse($memberFollowUps as $followUp)
                    <x-tl.follow-up-card :followUp="$followUp" />
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No follow-ups for this member.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Bila history tab --}}
        <div
            x-show="activeTab === 'bilas'"
            x-cloak
            role="tabpanel"
            aria-label="Bila history"
        >
            <div class="space-y-3">
                @forelse($memberBilas as $bila)
                    <div
                        x-data="{ expanded: false }"
                        class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                    >
                        <button
                            type="button"
                            x-on:click="expanded = !expanded"
                            x-bind:aria-expanded="expanded"
                            class="flex w-full items-center justify-between px-5 py-4 text-left"
                        >
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90">
                                Bila — {{ \Carbon\Carbon::parse($bila->scheduled_date)->format('d F Y') }}
                            </span>
                            <svg
                                class="h-4 w-4 text-gray-400 transition"
                                x-bind:class="expanded ? 'rotate-180' : ''"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <div
                            x-show="expanded"
                            x-cloak
                            class="border-t border-gray-100 px-5 py-4 dark:border-gray-800"
                        >
                            @if($bila->notes)
                                <x-tl.markdown-content :content="$bila->notes" />
                            @else
                                <p class="text-sm text-gray-400 dark:text-gray-500">No notes recorded.</p>
                            @endif

                            <div class="mt-3">
                                <a
                                    href="{{ route('bilas.show', $bila->id) }}"
                                    class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Open full bila
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No bilas yet for this member.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Agreements tab --}}
        <div
            x-show="activeTab === 'agreements'"
            x-cloak
            role="tabpanel"
            aria-label="Agreements"
            x-data="agreementManager({ teamMemberId: {{ $member->id }}, agreements: {{ $memberAgreements->toJson() }} })"
        >
            {{-- Add agreement button --}}
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Agreements
                    <span class="ml-1 text-xs text-gray-400" x-text="'(' + agreements.length + ')'"></span>
                </h3>
                <button
                    type="button"
                    x-on:click="openAddForm()"
                    x-show="!isAdding && editingId === null"
                    class="flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add agreement
                </button>
            </div>

            {{-- Add form --}}
            <div x-show="isAdding" x-cloak class="mb-4 rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                <form x-on:submit.prevent="submitForm()">
                    <div class="space-y-3">
                        <div>
                            <label for="agreement-description" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea
                                id="agreement-description"
                                x-model="form.description"
                                rows="2"
                                required
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                placeholder="What was agreed upon?"
                            ></textarea>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <div class="flex-1" style="min-width: 10rem" x-data="datePicker()">
                                <label for="agreement-agreed-date" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Agreed date
                                </label>
                                <input
                                    id="agreement-agreed-date"
                                    type="text"
                                    x-ref="input"
                                    x-model="form.agreed_date"
                                    required
                                    placeholder="YYYY-MM-DD"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                            </div>
                            <div class="flex-1" style="min-width: 10rem" x-data="datePicker()">
                                <label for="agreement-follow-up-date" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Follow-up date (optional)
                                </label>
                                <input
                                    id="agreement-follow-up-date"
                                    type="text"
                                    x-ref="input"
                                    x-model="form.follow_up_date"
                                    placeholder="YYYY-MM-DD"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                >
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <button
                            type="submit"
                            x-bind:disabled="isSubmitting || !form.description || !form.agreed_date"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50 dark:hover:bg-blue-500"
                        >
                            <span x-show="!isSubmitting">Save</span>
                            <span x-show="isSubmitting" x-cloak>Saving&hellip;</span>
                        </button>
                        <button
                            type="button"
                            x-on:click="closeForm()"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            {{-- Agreement list --}}
            <div class="space-y-3">
                <template x-for="agreement in agreements" x-bind:key="agreement.id">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                        {{-- View mode --}}
                        <div x-show="editingId !== agreement.id">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm text-gray-800 dark:text-white/90" x-text="agreement.description"></p>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        x-on:click="startEdit(agreement.id)"
                                        class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                        title="Edit agreement"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="deleteConfirmId = agreement.id"
                                        class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        title="Delete agreement"
                                        aria-label="Delete agreement"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="'Agreed: ' + formatDate(agreement.agreed_date)"></span>
                                <span x-show="agreement.follow_up_date" x-text="'Follow-up: ' + formatDate(agreement.follow_up_date ?? '')"></span>
                            </div>

                            {{-- Delete confirmation --}}
                            <div
                                x-show="deleteConfirmId === agreement.id"
                                x-cloak
                                class="mt-3 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/10"
                            >
                                <p class="flex-1 text-xs text-red-700 dark:text-red-400">Delete this agreement?</p>
                                <button
                                    type="button"
                                    x-on:click="deleteAgreement(agreement.id)"
                                    class="rounded-lg bg-red-600 px-3 py-1 text-xs font-medium text-white transition hover:bg-red-700"
                                >
                                    Delete
                                </button>
                                <button
                                    type="button"
                                    x-on:click="deleteConfirmId = null"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-xs text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        {{-- Edit mode --}}
                        <div x-show="editingId === agreement.id" x-cloak>
                            <form x-on:submit.prevent="submitForm()">
                                <div class="space-y-3">
                                    <div>
                                        <label x-bind:for="'edit-desc-' + agreement.id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                            Description
                                        </label>
                                        <textarea
                                            x-bind:id="'edit-desc-' + agreement.id"
                                            x-model="form.description"
                                            rows="2"
                                            required
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                        ></textarea>
                                    </div>
                                    <div class="flex flex-wrap gap-3">
                                        <div class="flex-1" style="min-width: 10rem" x-data="datePicker()">
                                            <label x-bind:for="'edit-date-' + agreement.id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                Agreed date
                                            </label>
                                            <input
                                                x-bind:id="'edit-date-' + agreement.id"
                                                type="text"
                                                x-ref="input"
                                                x-model="form.agreed_date"
                                                required
                                                placeholder="YYYY-MM-DD"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >
                                        </div>
                                        <div class="flex-1" style="min-width: 10rem" x-data="datePicker()">
                                            <label x-bind:for="'edit-followup-' + agreement.id" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                Follow-up date (optional)
                                            </label>
                                            <input
                                                x-bind:id="'edit-followup-' + agreement.id"
                                                type="text"
                                                x-ref="input"
                                                x-model="form.follow_up_date"
                                                placeholder="YYYY-MM-DD"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <button
                                        type="submit"
                                        x-bind:disabled="isSubmitting || !form.description || !form.agreed_date"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50 dark:hover:bg-blue-500"
                                    >
                                        <span x-show="!isSubmitting">Update</span>
                                        <span x-show="isSubmitting" x-cloak>Saving&hellip;</span>
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="closeForm()"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>

                <p
                    x-show="agreements.length === 0"
                    class="py-6 text-center text-sm text-gray-400 dark:text-gray-500"
                >
                    No agreements recorded.
                </p>
            </div>
        </div>

        {{-- Notes tab --}}
        <div
            x-show="activeTab === 'notes'"
            x-cloak
            role="tabpanel"
            aria-label="Notes"
        >
            <div class="space-y-3">
                @forelse($memberNotes as $note)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $note->title }}
                            </h3>
                            <a
                                href="{{ route('notes.show', $note->id) }}"
                                class="shrink-0 text-xs text-blue-600 hover:underline dark:text-blue-400"
                            >
                                Open
                            </a>
                        </div>

                        @if($note->content)
                            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $note->content }}
                            </p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-1">
                            @foreach($note->tags ?? [] as $tag)
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No notes linked to this member.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
