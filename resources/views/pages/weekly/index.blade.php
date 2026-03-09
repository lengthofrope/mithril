@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Weekly Reflection" />

    {{-- Current week --}}
    <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- Auto-generated summary + charts --}}
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
                {{-- Stat counters --}}
                <div class="mb-5 grid grid-cols-5 gap-2">
                    <div class="rounded-lg bg-green-50 p-2.5 text-center dark:bg-green-500/10">
                        <p class="text-lg font-semibold text-green-700 dark:text-green-400">
                            {{ $weekStats['tasks_completed'] ?? 0 }}
                        </p>
                        <p class="text-[0.65rem] leading-tight text-green-600 dark:text-green-500">Tasks done</p>
                    </div>
                    <div class="rounded-lg bg-orange-50 p-2.5 text-center dark:bg-orange-500/10">
                        <p class="text-lg font-semibold text-orange-700 dark:text-orange-400">
                            {{ $weekStats['tasks_open'] ?? 0 }}
                        </p>
                        <p class="text-[0.65rem] leading-tight text-orange-600 dark:text-orange-500">Still open</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 p-2.5 text-center dark:bg-blue-500/10">
                        <p class="text-lg font-semibold text-blue-700 dark:text-blue-400">
                            {{ $weekStats['follow_ups_handled'] ?? 0 }}
                        </p>
                        <p class="text-[0.65rem] leading-tight text-blue-600 dark:text-blue-500">Follow-ups</p>
                    </div>
                    <div class="rounded-lg bg-purple-50 p-2.5 text-center dark:bg-purple-500/10">
                        <p class="text-lg font-semibold text-purple-700 dark:text-purple-400">
                            {{ $weekStats['bilas_held'] ?? 0 }}
                        </p>
                        <p class="text-[0.65rem] leading-tight text-purple-600 dark:text-purple-500">Bilas</p>
                    </div>
                    <div class="rounded-lg bg-teal-50 p-2.5 text-center dark:bg-teal-500/10">
                        <p class="text-lg font-semibold text-teal-700 dark:text-teal-400">
                            {{ $weekStats['notes_written'] ?? 0 }}
                        </p>
                        <p class="text-[0.65rem] leading-tight text-teal-600 dark:text-teal-500">Notes</p>
                    </div>
                </div>

                {{-- Charts --}}
                <div class="grid grid-cols-2 gap-4">
                    {{-- Task status donut --}}
                    <div>
                        <h3 class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Task status</h3>
                        <div
                            x-data="weeklyChart({
                                chartType: 'donut',
                                labels: @js($chartData['donut']['labels']),
                                series: @js($chartData['donut']['series']),
                                colors: @js($chartData['donut']['colors'])
                            })"
                        >
                            <div x-ref="chart"></div>
                        </div>
                    </div>

                    {{-- Activity breakdown bar --}}
                    <div>
                        <h3 class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Activity breakdown</h3>
                        <div
                            x-data="weeklyChart({
                                chartType: 'bar_horizontal',
                                labels: @js($chartData['bar']['labels']),
                                series: @js($chartData['bar']['series']),
                                colors: @js($chartData['bar']['colors'])
                            })"
                        >
                            <div x-ref="chart"></div>
                        </div>
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

                <x-tl.auto-save-status />
            </div>
        </div>
    </div>

    {{-- Past weeks --}}
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Past weeks</h2>

        {{-- Add past reflection --}}
        <div x-data="{ open: false }" class="relative">
            <button
                type="button"
                x-on:click="open = !open"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]"
            >
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add past week
            </button>

            <form
                x-show="open"
                x-cloak
                x-on:click.outside="open = false"
                method="POST"
                action="{{ route('weekly.store') }}"
                class="absolute right-0 z-10 mt-2 rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            >
                @csrf
                <label for="past-week-start" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                    Week starting on
                </label>
                <input
                    type="date"
                    id="past-week-start"
                    name="week_start"
                    max="{{ now()->subWeek()->startOfWeek()->toDateString() }}"
                    required
                    class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                />
                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700"
                >
                    Create reflection
                </button>
            </form>
        </div>
    </div>

    @if($pastReflections->isNotEmpty())
        <div class="space-y-2">
            @foreach($pastReflections as $pastReflection)
                <div
                    x-data="{ expanded: false, confirmDelete: false }"
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <div class="flex w-full items-center justify-between px-5 py-4">
                        <button
                            type="button"
                            x-on:click="expanded = !expanded"
                            x-bind:aria-expanded="expanded"
                            class="flex flex-1 items-center gap-2 text-left"
                        >
                            <svg
                                class="h-4 w-4 text-gray-400 transition"
                                x-bind:class="expanded ? 'rotate-180' : ''"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90">
                                Week of {{ \Carbon\Carbon::parse($pastReflection->week_start)->format('d F Y') }}
                            </span>
                        </button>

                        <div class="relative ml-2 flex items-center">
                            <button
                                type="button"
                                x-show="!confirmDelete"
                                x-on:click="confirmDelete = true"
                                class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                title="Delete reflection"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>

                            <form
                                x-show="confirmDelete"
                                x-cloak
                                x-on:click.outside="confirmDelete = false"
                                method="POST"
                                action="{{ route('weekly.destroy', $pastReflection->id) }}"
                                class="flex items-center gap-1"
                            >
                                @csrf
                                @method('DELETE')
                                <span class="text-xs text-red-600 dark:text-red-400">Delete?</span>
                                <button
                                    type="submit"
                                    class="rounded bg-red-600 px-2 py-0.5 text-xs font-medium text-white transition hover:bg-red-700"
                                >
                                    Yes
                                </button>
                                <button
                                    type="button"
                                    x-on:click="confirmDelete = false"
                                    class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    No
                                </button>
                            </form>
                        </div>
                    </div>

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

                        <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Reflection
                        </h3>

                        <div
                            x-data="Object.assign(
                                markdownEditor({ field: 'reflection' }),
                                autoSaveField({
                                    endpoint: '{{ route('weekly.update', $pastReflection->id) }}',
                                    field: 'reflection'
                                })
                            )"
                            x-init="content = @js($pastReflection->reflection ?? ''); value = content;"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <button
                                    type="button"
                                    x-on:click="isPreview = false"
                                    x-bind:class="!isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400'"
                                    class="rounded-md px-2 py-0.5 text-xs font-medium transition"
                                >
                                    Write
                                </button>
                                <button
                                    type="button"
                                    x-on:click="togglePreview()"
                                    x-bind:class="isPreview ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400'"
                                    class="rounded-md px-2 py-0.5 text-xs font-medium transition"
                                >
                                    Preview
                                </button>
                            </div>

                            <div x-show="!isPreview">
                                <label for="reflection-{{ $pastReflection->id }}" class="sr-only">Reflection for week of {{ \Carbon\Carbon::parse($pastReflection->week_start)->format('d F Y') }}</label>
                                <textarea
                                    id="reflection-{{ $pastReflection->id }}"
                                    name="reflection"
                                    x-model="content"
                                    x-on:input="value = content"
                                    rows="6"
                                    placeholder="Write your reflection for this week…"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                                ></textarea>
                            </div>

                            <div
                                x-show="isPreview"
                                x-cloak
                                x-html="preview"
                                class="prose prose-sm max-w-none min-h-16 text-gray-700 dark:prose-invert dark:text-gray-300"
                            ></div>

                            <x-tl.auto-save-status />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No past reflections yet.</p>
    @endif
@endsection
