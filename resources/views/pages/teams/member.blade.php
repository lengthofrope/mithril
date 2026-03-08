@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="{{ $member->name }}" />

    {{-- Member header --}}
    <div class="mb-6 flex flex-wrap items-center gap-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.06]">
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

        <div class="relative shrink-0">
            <x-tl.team-member-avatar :member="$member" size="xl" />
            <span
                class="absolute bottom-0 right-0 h-4 w-4 rounded-full border-2 border-white dark:border-gray-900 {{ $statusColor }}"
                title="{{ $statusLabel }}"
            ></span>
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
        </div>

        <div class="flex flex-wrap items-center gap-2" x-data="{ deleteOpen: false }">
            @if($member->next_bila_date)
                <a
                    href="{{ route('bilas.create', ['member_id' => $member->id]) }}"
                    class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:hover:bg-blue-500"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        <line x1="12" y1="15" x2="12" y2="19"/><line x1="10" y1="17" x2="14" y2="17"/>
                    </svg>
                    Schedule bila
                </a>
            @endif

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
                        class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.06]"
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
        >
            <div class="space-y-3">
                @forelse($memberAgreements as $agreement)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.06]">
                        <p class="text-sm text-gray-800 dark:text-white/90">
                            {{ $agreement->description }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span>Agreed: {{ \Carbon\Carbon::parse($agreement->agreed_date)->format('d M Y') }}</span>
                            @if($agreement->follow_up_date)
                                <span>Follow-up: {{ \Carbon\Carbon::parse($agreement->follow_up_date)->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No agreements recorded.
                    </p>
                @endforelse
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
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.06]">
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
