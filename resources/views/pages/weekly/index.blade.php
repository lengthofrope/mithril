@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Weekly Reflection" />

    {{-- Current week --}}
    <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- Auto-generated summary --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                    This week's summary
                    <span class="ml-2 text-xs font-normal text-gray-400 dark:text-gray-500">
                        {{ \Carbon\Carbon::parse($currentReflection->week_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($currentReflection->week_end)->format('d M Y') }}
                    </span>
                </h2>
            </div>

            <div class="p-5">
                @if($currentReflection->summary)
                    <x-tl.markdown-content :content="$currentReflection->summary" />
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                        No summary yet — check back at the end of the week.
                    </p>
                @endif

                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-green-50 p-3 text-center dark:bg-green-500/10">
                        <p class="text-xl font-semibold text-green-700 dark:text-green-400">
                            {{ $weekStats['tasks_completed'] ?? 0 }}
                        </p>
                        <p class="text-xs text-green-600 dark:text-green-500">Tasks completed</p>
                    </div>
                    <div class="rounded-lg bg-orange-50 p-3 text-center dark:bg-orange-500/10">
                        <p class="text-xl font-semibold text-orange-700 dark:text-orange-400">
                            {{ $weekStats['tasks_open'] ?? 0 }}
                        </p>
                        <p class="text-xs text-orange-600 dark:text-orange-500">Still open</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 p-3 text-center dark:bg-blue-500/10">
                        <p class="text-xl font-semibold text-blue-700 dark:text-blue-400">
                            {{ $weekStats['follow_ups_handled'] ?? 0 }}
                        </p>
                        <p class="text-xs text-blue-600 dark:text-blue-500">Follow-ups handled</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reflection text area --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                    My reflection
                </h2>
            </div>

            <div
                class="p-5"
                x-data="Object.assign(
                    markdownEditor({ field: 'reflection' }),
                    autoSaveField({
                        endpoint: '{{ route('weekly.update', $currentReflection->id) }}',
                        field: 'reflection'
                    })
                )"
                x-init="content = @js($currentReflection->reflection ?? ''); value = content;"
            >
                <div class="mb-3 flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="isPreview = false"
                        x-bind:class="!isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                    >
                        Write
                    </button>
                    <button
                        type="button"
                        x-on:click="togglePreview()"
                        x-bind:class="isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400'"
                        class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                    >
                        Preview
                    </button>
                </div>

                <div x-show="!isPreview">
                    <label for="reflection-editor" class="sr-only">Weekly reflection</label>
                    <textarea
                        id="reflection-editor"
                        name="reflection"
                        x-model="content"
                        x-on:input="value = content"
                        rows="12"
                        placeholder="What went well? What would you do differently?…"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                    ></textarea>
                </div>

                <div
                    x-show="isPreview"
                    x-cloak
                    x-html="preview"
                    class="prose prose-sm max-w-none min-h-32 text-gray-700 dark:prose-invert dark:text-gray-300"
                ></div>

                <div class="mt-2 flex h-4 items-center" aria-live="polite" aria-atomic="true">
                    <span x-show="status === 'saving'" x-cloak class="flex items-center gap-1 text-xs text-gray-400">
                        <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Saving…
                    </span>
                    <span x-show="status === 'saved'" x-cloak class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Saved
                    </span>
                    <span x-show="status === 'error'" x-cloak class="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                        Failed to save
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Past weeks accordion --}}
    @if($pastReflections->isNotEmpty())
        <div>
            <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Past weeks</h2>

            <div class="space-y-2">
                @foreach($pastReflections as $pastReflection)
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
                                Week of {{ \Carbon\Carbon::parse($pastReflection->week_start)->format('d F Y') }}
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
                            @if($pastReflection->summary)
                                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    Summary
                                </h3>
                                <x-tl.markdown-content :content="$pastReflection->summary" />
                            @endif

                            @if($pastReflection->reflection)
                                <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    Reflection
                                </h3>
                                <x-tl.markdown-content :content="$pastReflection->reflection" />
                            @endif

                            @if(!$pastReflection->summary && !$pastReflection->reflection)
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No notes for this week.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
