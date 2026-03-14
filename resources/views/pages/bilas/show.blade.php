@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :items="$breadcrumbs" />

    {{-- Member info + date --}}
    <div class="mb-6 flex flex-wrap items-center gap-5 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        @if($bila->teamMember)
            <x-tl.team-member-avatar :member="$bila->teamMember" size="lg" />
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $bila->teamMember->name }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $bila->teamMember->role }}
                </p>
            </div>
        @endif

        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <x-tl.auto-save-field
                :endpoint="route('bilas.update', $bila->id)"
                field="scheduled_date"
                :value="$bila->scheduled_date->toDateString()"
                type="date"
                label=""
            />
        </div>
    </div>

    {{-- Navigation to prev/next bila --}}
    <div class="mb-6 flex items-center justify-between">
        @if($previousBila)
            <a
                href="{{ route('bilas.show', $previousBila->id) }}"
                class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                Previous bila
            </a>
        @else
            <div></div>
        @endif

        @if($nextBila)
            <a
                href="{{ route('bilas.show', $nextBila->id) }}"
                class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-400 dark:hover:bg-gray-800"
            >
                Next bila
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
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Prep items</h2>

                <form
                    action="{{ route('prep-items.store') }}"
                    class="flex items-center gap-2"
                    x-on:submit.prevent="addPrepItem($event)"
                >
                    <input type="hidden" name="bila_id" value="{{ $bila->id }}">
                    <input type="hidden" name="team_member_id" value="{{ $bila->team_member_id }}">
                    <label for="new-prep-item" class="sr-only">New prep item</label>
                    <input
                        id="new-prep-item"
                        type="text"
                        name="content"
                        placeholder="Add prep item…"
                        required
                        class="w-48 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    >
                    <button
                        type="submit"
                        class="flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add
                    </button>
                </form>
            </div>

            <x-tl.sortable-container
                modelType="bila_prep_item"
                :endpoint="route('reorder')"
                :containerId="'prep-items-' . $bila->id"
            >
                @forelse($bila->prepItems->sortBy('sort_order') as $prepItem)
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

                        <span class="flex-1 text-sm text-gray-800 dark:text-white/90 {{ $prepItem->is_discussed ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                            {{ $prepItem->content }}
                        </span>

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
                    autoSaveField({ endpoint: '{{ route('bilas.update', $bila->id) }}', field: 'notes' })
                )"
                x-init="content = @js($bila->notes ?? ''); value = content;"
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
                    <label for="bila-notes-editor" class="sr-only">Bila notes</label>
                    <textarea
                        id="bila-notes-editor"
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
                :parent="$bila"
                parentType="bilas"
                :activities="$bila->getActivityFeed()"
            />
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-4 flex items-center gap-3">
        <a
            href="{{ route('bilas.index') }}"
            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            &larr; Back to bilas
        </a>

        <div class="ml-auto flex items-center gap-2">
            @if(!$bila->is_done)
                <form method="POST" action="{{ route('bilas.done', $bila->id) }}" class="inline">
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
                <form method="POST" action="{{ route('bilas.undone', $bila->id) }}" class="inline">
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

            <form method="POST" action="{{ route('bilas.destroy', $bila->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    onclick="return confirm('Delete this bila?')"
                    class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                >
                    Delete bila
                </button>
            </form>
        </div>
    </div>
@endsection
