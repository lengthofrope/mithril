@props([
    'title'   => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'triggerId',
])

<div
    x-data="confirmDialog({
        title: {{ json_encode($title) }},
        message: {{ json_encode($message) }},
        onConfirm() {
            const form = document.getElementById('confirm-form-{{ $triggerId }}');
            if (form) form.submit();
        },
    })"
>
    {{-- Trigger slot --}}
    <div id="trigger-{{ $triggerId }}" x-on:click="open()">
        {{ $trigger ?? '' }}
    </div>

    {{-- Modal overlay --}}
    <div
        x-show="isOpen"
        x-cloak
        x-on:keydown.escape.window="cancel()"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        x-bind:aria-labelledby="'dialog-title-{{ $triggerId }}'"
    >
        <div
            x-on:click="cancel()"
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
                id="dialog-title-{{ $triggerId }}"
                class="text-base font-semibold text-gray-900 dark:text-white"
                x-text="title"
            ></h2>

            <p
                class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                x-text="message"
            ></p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    x-on:click="cancel()"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    x-on:click="confirm()"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 dark:hover:bg-red-500"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form submitted on confirm --}}
    <div id="confirm-form-wrapper-{{ $triggerId }}">
        {{ $form ?? '' }}
    </div>
</div>
