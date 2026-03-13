@php
    $notifications = \App\Models\SystemNotification::active()
        ->notDismissedBy(auth()->user())
        ->latest()
        ->get();
@endphp

@if($notifications->isNotEmpty())
    <div class="mb-4 space-y-3">
        @foreach($notifications as $notification)
            <div
                x-data="systemNotification({{ $notification->id }})"
                x-show="isVisible"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
            >
                <x-ui.alert
                    :variant="$notification->variant->value"
                    :title="$notification->title"
                    :showLink="!empty($notification->link_url)"
                    :linkHref="$notification->link_url ?? '#'"
                    :linkText="$notification->link_text ?? 'Learn more'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $notification->message }}</p>
                        <button
                            type="button"
                            class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            :disabled="isDismissing"
                            @click="dismiss()"
                            aria-label="Dismiss notification"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </x-ui.alert>
            </div>
        @endforeach
    </div>
@endif
