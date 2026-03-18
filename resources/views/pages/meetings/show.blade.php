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
    </div>

    {{-- Recording section --}}
    <div class="mb-6 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
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

    {{-- Transcription section --}}
    <div
        class="mb-6 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        x-data="{
            status: @js($meeting->transcription?->status?->value),
            content: @js($meeting->transcription?->content ?? ''),
            errorMessage: @js($meeting->transcription?->error_message ?? ''),
            showManualInput: false,
            manualContent: '',
            polling: false,

            init() {
                if (this.status === 'pending' || this.status === 'processing') {
                    this.startPolling();
                }
            },

            async startPolling() {
                this.polling = true;
                while (this.polling && (this.status === 'pending' || this.status === 'processing')) {
                    await new Promise(r => setTimeout(r, 3000));
                    try {
                        const response = await fetch('/api/v1/meetings/{{ $meeting->id }}/transcription', {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (response.ok) {
                            const data = await response.json();
                            this.status = data.data?.status;
                            this.content = data.data?.content ?? '';
                            this.errorMessage = data.data?.error_message ?? '';
                            if (this.status === 'completed' || this.status === 'failed') {
                                this.polling = false;
                            }
                        }
                    } catch { /* continue polling */ }
                }
            },

            async retry() {
                const response = await fetch('/api/v1/meetings/{{ $meeting->id }}/transcription/retry', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.status = 'pending';
                    this.errorMessage = '';
                    this.startPolling();
                }
            },

            async saveManual() {
                const response = await fetch('/api/v1/meetings/{{ $meeting->id }}/transcription/manual', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ content: this.manualContent }),
                });
                if (response.ok) {
                    this.content = this.manualContent;
                    this.status = 'completed';
                    this.showManualInput = false;
                }
            },
        }"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Transcription</h2>
            <div class="flex items-center gap-2">
                <template x-if="status === 'failed'">
                    <button
                        type="button"
                        x-on:click="retry()"
                        class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/50 dark:bg-amber-500/10 dark:text-amber-400"
                    >Retry</button>
                </template>
                <button
                    type="button"
                    x-on:click="showManualInput = !showManualInput"
                    class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                    x-text="showManualInput ? 'Cancel' : 'Manual input'"
                ></button>
            </div>
        </div>

        <div class="p-5">
            {{-- Pending / Processing --}}
            <template x-if="status === 'pending' || status === 'processing'">
                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="status === 'pending' ? 'Waiting to start transcription…' : 'Transcribing audio…'"></span>
                </div>
            </template>

            {{-- Failed --}}
            <template x-if="status === 'failed'">
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    <p class="font-medium">Transcription failed</p>
                    <p class="mt-1 text-xs" x-text="errorMessage"></p>
                </div>
            </template>

            {{-- Completed --}}
            <template x-if="status === 'completed' && !showManualInput">
                <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" x-text="content"></div>
            </template>

            {{-- No transcription yet --}}
            <template x-if="!status && !showManualInput">
                <p class="text-sm text-gray-400 dark:text-gray-500">No transcription available yet. Record or upload audio to start.</p>
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

    {{-- AI Extractions review --}}
    @if($meeting->extractions->isNotEmpty() || ($meeting->transcription && $meeting->transcription->status->value === 'completed'))
        <div
            class="mb-6 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
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
                csrfToken: document.querySelector('meta[name=csrf-token]')?.content ?? '',
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
                    <button type="button" x-on:click="reExtract()" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400" :disabled="loading">Re-extract</button>
                </div>
            </div>

            {{-- Summary --}}
            @if($meeting->summary)
                <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Summary</p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $meeting->summary }}</p>
                </div>
            @endif

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
                                        @php
                                            $typeColors = [
                                                'task' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                                'follow_up' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                'agreement' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'decision' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                                            ];
                                        @endphp
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

                        {{-- Edit mode --}}
                        <template x-if="editingId === extraction.id">
                            <div class="space-y-2">
                                <input type="text" x-model="editContent" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <div class="flex items-center gap-2">
                                    <select x-model="editPriority" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">No priority</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="high">High</option>
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                    </select>
                                    <input type="date" x-model="editDeadline" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <button type="button" x-on:click="acceptWithEdits(extraction)" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700" :disabled="loading">Save & accept</button>
                                    <button type="button" x-on:click="cancelEdit()" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400">Cancel</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <template x-if="extractions.length === 0">
                <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">No extractions yet. Complete a transcription to extract insights.</p>
            </template>
        </div>
    @endif

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
