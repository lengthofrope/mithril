@props(['parent', 'parentType', 'activities'])

<div
    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    x-data="activityInput({ parentType: '{{ $parentType }}', parentId: {{ $parent->id }} })"
    x-on:confirm-delete-activity="confirmDelete($event.detail.id)"
>
    {{-- Card header --}}
    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Activity</h2>
    </div>

    <div class="p-5">
        {{-- Tab navigation --}}
        <div class="mb-4 flex gap-4 border-b border-gray-100 dark:border-gray-800" role="tablist" aria-label="Activity type">
            <button
                type="button"
                role="tab"
                x-bind:aria-selected="activeTab === 'comment'"
                x-on:click="setTab('comment')"
                x-bind:class="activeTab === 'comment'
                    ? 'border-b-2 border-brand-500 text-brand-600 dark:text-brand-400'
                    : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                class="-mb-px pb-2 text-sm font-medium transition"
            >
                Comment
            </button>
            <button
                type="button"
                role="tab"
                x-bind:aria-selected="activeTab === 'link'"
                x-on:click="setTab('link')"
                x-bind:class="activeTab === 'link'
                    ? 'border-b-2 border-brand-500 text-brand-600 dark:text-brand-400'
                    : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                class="-mb-px pb-2 text-sm font-medium transition"
            >
                Link
            </button>
            <button
                type="button"
                role="tab"
                x-bind:aria-selected="activeTab === 'file'"
                x-on:click="setTab('file')"
                x-bind:class="activeTab === 'file'
                    ? 'border-b-2 border-brand-500 text-brand-600 dark:text-brand-400'
                    : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                class="-mb-px pb-2 text-sm font-medium transition"
            >
                File
            </button>
        </div>

        {{-- Comment tab --}}
        <div x-show="activeTab === 'comment'" role="tabpanel" aria-label="Comment input">
            <label for="activity-comment" class="sr-only">Write a comment</label>
            <textarea
                id="activity-comment"
                x-model="body"
                rows="3"
                placeholder="Write a comment…"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
            ></textarea>
            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    x-on:click="submitComment()"
                    x-bind:disabled="isSubmitting || !body.trim()"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span x-show="!isSubmitting">Post comment</span>
                    <span x-show="isSubmitting" x-cloak>Posting…</span>
                </button>
            </div>
        </div>

        {{-- Link tab --}}
        <div x-show="activeTab === 'link'" x-cloak role="tabpanel" aria-label="Link input">
            <div class="flex flex-col gap-3">
                <div>
                    <label for="activity-link-url" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">URL <span class="text-red-500" aria-hidden="true">*</span></label>
                    <input
                        id="activity-link-url"
                        type="url"
                        x-model="url"
                        placeholder="https://…"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                    >
                </div>
                <div>
                    <label for="activity-link-title" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                    <input
                        id="activity-link-title"
                        type="text"
                        x-model="linkTitle"
                        placeholder="Link title (optional)"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                    >
                </div>
                <div>
                    <label for="activity-link-body" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                    <input
                        id="activity-link-body"
                        type="text"
                        x-model="linkBody"
                        placeholder="Short description (optional)"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:focus:border-blue-500"
                    >
                </div>
            </div>
            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    x-on:click="submitLink()"
                    x-bind:disabled="isSubmitting || !url.trim()"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span x-show="!isSubmitting">Add link</span>
                    <span x-show="isSubmitting" x-cloak>Adding…</span>
                </button>
            </div>
        </div>

        {{-- File tab --}}
        <div x-show="activeTab === 'file'" x-cloak role="tabpanel" aria-label="File upload">
            <div class="flex flex-col gap-3">
                <label
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-6 text-center transition hover:border-gray-400 dark:border-gray-700 dark:hover:border-gray-600"
                >
                    <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                    </svg>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Click to select files <span class="text-xs">(max {{ 5 }})</span>
                    </span>
                    <input
                        type="file"
                        multiple
                        class="sr-only"
                        x-on:change="addFiles($event.target.files)"
                        aria-label="Select files to upload"
                    >
                </label>

                <template x-if="files.length > 0">
                    <ul class="flex flex-col gap-1.5" role="list">
                        <template x-for="(file, index) in files" :key="index">
                            <li class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                                <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300" x-text="file.name"></span>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="(file.size / 1024).toFixed(1) + ' KB'"></span>
                                <button
                                    type="button"
                                    x-on:click="removeFile(index)"
                                    class="shrink-0 rounded p-0.5 text-gray-400 transition hover:text-red-500"
                                    aria-label="Remove file"
                                >
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>

            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    x-on:click="submitFiles()"
                    x-bind:disabled="isSubmitting || files.length === 0"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span x-show="!isSubmitting">Upload</span>
                    <span x-show="isSubmitting" x-cloak>Uploading…</span>
                </button>
            </div>
        </div>

        {{-- Error message --}}
        <div
            x-show="error !== null"
            x-cloak
            class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-700/50 dark:bg-red-500/10 dark:text-red-400"
            role="alert"
            aria-live="polite"
            x-text="error"
        ></div>

        {{-- Feed list (refreshable) --}}
        <div
            class="mt-6"
            x-data="refreshable({ url: '{{ route('partials.activity-feed', ['type' => $parentType, 'id' => $parent->id]) }}', topics: ['activities'], pollInterval: 30000 })"
        >
            <div data-refresh-target>
                @include('partials.activity-feed', ['activities' => $activities])
            </div>
        </div>

        {{-- Delete confirmation modal --}}
        <div
            x-show="confirmDeleteId !== null"
            x-cloak
            x-on:keydown.escape.window="cancelDelete()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Confirm deletion"
        >
            <div x-on:click="cancelDelete()" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

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
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Delete this activity?</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">This will permanently remove this entry and any attached files.</p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        x-on:click="cancelDelete()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="deleteActivity()"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
