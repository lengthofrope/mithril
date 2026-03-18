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

                {{-- Type badge + status + date --}}
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @php
                        $typeBadge = match($meeting->type->value) {
                            'one_on_one' => ['label' => '1-on-1', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
                            'team' => ['label' => 'Team meeting', 'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'],
                            default => ['label' => 'Other', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
                        };
                        $statusBadge = match($meeting->status->value) {
                            'scheduled' => ['label' => 'Scheduled', 'class' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'],
                            'in_progress' => ['label' => 'In progress', 'class' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400'],
                            'completed' => ['label' => 'Completed', 'class' => 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400'],
                            'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'],
                            default => ['label' => $meeting->status->value, 'class' => 'bg-gray-100 text-gray-600'],
                        };
                    @endphp

                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $typeBadge['class'] }}">{{ $typeBadge['label'] }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>

                    <span class="text-xs text-gray-400">&middot;</span>

                    <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <x-tl.auto-save-field
                            :endpoint="route('meetings.update', $meeting->id)"
                            field="scheduled_at"
                            :value="$meeting->scheduled_at->toDateString()"
                            type="date"
                            label=""
                        />
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
        @if($meeting->attendees->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Attendees:</span>
                @foreach($meeting->attendees as $attendee)
                    <div class="flex items-center gap-2">
                        <x-tl.team-member-avatar :member="$attendee" size="xs" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $attendee->name }}</span>
                        @if($attendee->role)
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $attendee->role }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Navigation to prev/next meeting --}}
    <div class="mb-6 flex items-center justify-between">
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Prep items --}}
        <div
            class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
            x-data="{
                newType: 'agenda_item',
                newDuration: '',
                newAssignee: '',
                async addPrepItem(event) {
                    const form = event.target;
                    const formData = new FormData(form);

                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(Object.fromEntries(formData)),
                    });

                    if (response.ok) {
                        window.location.reload();
                    }
                },
                async toggleDiscussed(url, isDiscussed) {
                    await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ is_discussed: isDiscussed }),
                    });

                    window.location.reload();
                },
                async deletePrepItem(url) {
                    await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });

                    window.location.reload();
                },
            }"
        >
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Prep items</h2>
            </div>

            {{-- Add new prep item form --}}
            <form
                action="{{ route('prep-items.store') }}"
                class="border-b border-gray-100 px-5 py-3 dark:border-gray-800"
                x-on:submit.prevent="addPrepItem($event)"
            >
                <input type="hidden" name="meeting_id" value="{{ $meeting->id }}">
                <div class="flex items-center gap-2">
                    <label for="new-prep-item" class="sr-only">New prep item</label>
                    <input
                        id="new-prep-item"
                        type="text"
                        name="content"
                        placeholder="Add prep item…"
                        required
                        class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                    <select name="type" x-model="newType" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="agenda_item">Agenda</option>
                        <option value="question">Question</option>
                        <option value="action">Action</option>
                    </select>
                    <input
                        type="number"
                        name="duration_minutes"
                        x-model="newDuration"
                        placeholder="Min"
                        min="1"
                        class="w-16 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    >
                    @if(count($attendeeOptions) > 0)
                        <select name="team_member_id" x-model="newAssignee" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Unassigned</option>
                            @foreach($attendeeOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button
                        type="submit"
                        class="flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add
                    </button>
                </div>
            </form>

            <x-tl.sortable-container
                modelType="meeting_prep_item"
                :endpoint="route('reorder')"
                :containerId="'prep-items-' . $meeting->id"
            >
                @forelse($meeting->prepItems->sortBy('sort_order') as $prepItem)
                    @php
                        $typeIcon = match($prepItem->type->value) {
                            'agenda_item' => ['icon' => 'A', 'class' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
                            'question' => ['icon' => 'Q', 'class' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
                            'action' => ['icon' => '!', 'class' => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
                            default => ['icon' => '?', 'class' => 'bg-gray-100 text-gray-600'],
                        };
                    @endphp
                    <div
                        data-id="{{ $prepItem->id }}"
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
                            @checked($prepItem->is_discussed)
                            x-on:change="toggleDiscussed('{{ route('prep-items.update', $prepItem->id) }}', $el.checked)"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                            aria-label="{{ $prepItem->content }}"
                        >

                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[0.625rem] font-bold {{ $typeIcon['class'] }}">
                            {{ $typeIcon['icon'] }}
                        </span>

                        <span class="flex-1 text-sm text-gray-800 dark:text-white/90 {{ $prepItem->is_discussed ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                            {{ $prepItem->content }}
                        </span>

                        @if($prepItem->duration_minutes)
                            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" title="Estimated duration">
                                {{ $prepItem->duration_minutes }}m
                            </span>
                        @endif

                        @if($prepItem->teamMember)
                            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                                {{ $prepItem->teamMember->name }}
                            </span>
                        @endif

                        <button
                            type="button"
                            x-on:click="deletePrepItem('{{ route('prep-items.destroy', $prepItem->id) }}')"
                            class="shrink-0 rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                            aria-label="Remove prep item"
                        >
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No prep items yet.
                    </p>
                @endforelse
            </x-tl.sortable-container>

            @if($meeting->prepItems->isNotEmpty())
                @php
                    $totalMinutes = $meeting->prepItems->sum('duration_minutes');
                @endphp
                @if($totalMinutes > 0)
                    <div class="border-t border-gray-100 px-5 py-2 text-xs text-gray-400 dark:border-gray-800 dark:text-gray-500">
                        Total estimated time: {{ $totalMinutes }} min
                    </div>
                @endif
            @endif
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
                        rows="14"
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

        {{-- Activity feed sidebar --}}
        <div>
            <x-tl.activity-feed
                :parent="$meeting"
                parentType="meetings"
                :activities="$meeting->getActivityFeed()"
            />
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-4 flex items-center gap-3">
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

            <form method="POST" action="{{ route('meetings.destroy', $meeting->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    onclick="return confirm('Delete this meeting?')"
                    class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                >
                    Delete meeting
                </button>
            </form>
        </div>
    </div>
@endsection
