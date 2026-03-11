@props(['task'])

@php
    $taskEndpoint = '/api/v1/tasks/' . $task->id;
    $intervalOptions = [
        ['value' => '', 'label' => '— Select —'],
        ['value' => 'daily', 'label' => 'Daily'],
        ['value' => 'weekly', 'label' => 'Weekly'],
        ['value' => 'biweekly', 'label' => 'Biweekly'],
        ['value' => 'monthly', 'label' => 'Monthly'],
        ['value' => 'custom', 'label' => 'Custom'],
    ];
@endphp

<div
    x-data="recurrenceSettings()"
    x-init="
        isRecurring = @js($task->is_recurring);
        interval = @js($task->recurrence_interval?->value);
        customDays = @js($task->recurrence_custom_days);
    "
>
    {{-- Recurring toggle --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div
            x-data="autoSaveField({ endpoint: @js($taskEndpoint), field: 'is_recurring' })"
            x-init="value = @js($task->is_recurring ? '1' : '0')"
            x-effect="isRecurring = value === '1' || value === true"
            class="flex flex-col gap-1.5"
        >
            <label for="asf-is_recurring" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Recurring
            </label>
            <select
                id="asf-is_recurring"
                name="is_recurring"
                x-model="value"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
            >
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
            <x-tl.auto-save-status />
        </div>

        {{-- Interval selector (shown when recurring) --}}
        <div x-show="showIntervalSelector()" x-cloak>
            <div
                x-data="autoSaveField({ endpoint: @js($taskEndpoint), field: 'recurrence_interval' })"
                x-init="value = @js($task->recurrence_interval?->value ?? '')"
                x-effect="interval = value || null"
                class="flex flex-col gap-1.5"
            >
                <label for="asf-recurrence_interval" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Interval
                </label>
                <select
                    id="asf-recurrence_interval"
                    name="recurrence_interval"
                    x-model="value"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-blue-500"
                >
                    @foreach($intervalOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <x-tl.auto-save-status />
            </div>
        </div>
    </div>

    {{-- Custom days input (shown when interval is custom) --}}
    <div x-show="showCustomDays()" x-cloak class="mt-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-tl.auto-save-field
                :endpoint="$taskEndpoint"
                field="recurrence_custom_days"
                :value="(string) ($task->recurrence_custom_days ?? '')"
                type="number"
                label="Repeat every (days)"
            />
        </div>
    </div>
</div>
