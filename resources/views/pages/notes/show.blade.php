@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    @php
        $noteEndpoint = '/api/v1/notes/' . $note->id;
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Main content --}}
        <div class="xl:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                {{-- Title --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$noteEndpoint"
                        field="title"
                        :value="$note->title"
                        type="text"
                        label="Title"
                    />
                </div>

                {{-- Date --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$noteEndpoint"
                        field="date"
                        :value="$note->date?->toDateString() ?? ''"
                        type="date"
                        label="Date"
                    />
                </div>

                {{-- Row: Team + Member (linked filtering) --}}
                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2"
                    x-data="{
                        allMembers: @js($memberOptions),
                        selectedTeamId: @js((string) ($note->team_id ?? '')),
                        get filteredMemberOptions() {
                            return this.selectedTeamId
                                ? this.allMembers.filter(m => String(m.team_id) === String(this.selectedTeamId))
                                : this.allMembers;
                        },
                    }"
                >
                    {{-- Team select --}}
                    <div
                        x-data="autoSaveField({ endpoint: @js($noteEndpoint), field: 'team_id' })"
                        x-init="value = @js((string) ($note->team_id ?? ''))"
                        x-effect="selectedTeamId = value"
                        class="flex flex-col gap-1.5"
                    >
                        <label for="asf-team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select
                            id="asf-team_id"
                            name="team_id"
                            x-model="value"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                        >
                            @foreach(array_merge([['value' => '', 'label' => '— None —']], $teamOptions) as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <x-tl.auto-save-status />
                    </div>

                    {{-- Member select (filtered by team) --}}
                    <div
                        x-data="autoSaveField({ endpoint: @js($noteEndpoint), field: 'team_member_id' })"
                        x-init="$nextTick(() => { value = @js((string) ($note->team_member_id ?? '')); })"
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

                {{-- Pinned --}}
                <div class="mb-6">
                    <x-tl.auto-save-field
                        :endpoint="$noteEndpoint"
                        field="is_pinned"
                        :value="$note->is_pinned ? '1' : '0'"
                        type="select"
                        label="Pinned"
                        :options="[['value' => '0', 'label' => 'No'], ['value' => '1', 'label' => 'Yes']]"
                    />
                </div>

                {{-- Content (markdown) --}}
                <div>
                    <x-tl.auto-save-field
                        :endpoint="$noteEndpoint"
                        field="content"
                        :value="$note->content ?? ''"
                        type="textarea"
                        label="Content"
                    />
                </div>

                {{-- Tags --}}
                <div
                    class="relative mt-6"
                    x-data="tagEditor({
                        endpoint: '{{ $noteEndpoint }}/tags',
                        initialTags: @js($note->tags->pluck('tag')->values()->all()),
                        allTags: @js($allTags->values()->all()),
                    })"
                >
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tags</label>
                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:focus-within:border-blue-500">
                        <template x-for="(tag, index) in tags" :key="tag">
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                <span x-text="tag"></span>
                                <button
                                    type="button"
                                    x-on:click="removeTag(index)"
                                    class="ml-0.5 text-gray-400 transition hover:text-red-500 dark:hover:text-red-400"
                                    aria-label="Remove tag"
                                >
                                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </span>
                        </template>
                        <input
                            type="text"
                            x-model="input"
                            x-on:keydown="handleKeydown($event)"
                            x-on:input="handleInput()"
                            x-on:blur="setTimeout(() => { showSuggestions = false; addTag(); }, 150)"
                            placeholder="Add tag…"
                            class="min-w-[6rem] flex-1 border-0 bg-transparent p-0 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-white/90 dark:placeholder:text-gray-500"
                        >
                    </div>

                    {{-- Autocomplete suggestions --}}
                    <div
                        x-show="showSuggestions && suggestions.length > 0"
                        x-cloak
                        class="absolute z-10 mt-1 max-h-40 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    >
                        <template x-for="(suggestion, sIndex) in suggestions" :key="suggestion">
                            <button
                                type="button"
                                x-text="suggestion"
                                x-on:mousedown.prevent="selectSuggestion(suggestion)"
                                :class="sIndex === selectedIndex
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'text-gray-700 dark:text-gray-300'"
                                class="block w-full px-3 py-1.5 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-white/5"
                            ></button>
                        </template>
                    </div>

                    <x-tl.auto-save-status />
                </div>
            </div>

            {{-- Actions --}}
            <div class="mt-4 flex items-center gap-3">
                <a
                    href="{{ route('notes.index') }}"
                    class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                >
                    &larr; Back to notes
                </a>

                <div class="ml-auto">
                    <form method="POST" action="{{ route('notes.destroy', $note->id) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            onclick="return confirm('Delete this note?')"
                            class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                        >
                            Delete note
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Activity feed sidebar --}}
        <div class="xl:col-span-1">
            <x-tl.activity-feed
                :parent="$note"
                parentType="notes"
                :activities="$note->getActivityFeed()"
            />
        </div>
    </div>
@endsection
