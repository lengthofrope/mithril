@props(['emails' => collect()])

@php
    use App\Services\EmailActionService;

    $actionService = app(EmailActionService::class);

    /**
     * Serialize an email's links for the Alpine emailActions component.
     */
    $linksJson = function (\App\Models\Email $email): string {
        $links = $email->emailLinks ?? collect();

        return $links->map(fn ($link): array => [
            'id'            => $link->id,
            'email_id'      => $link->email_id,
            'email_subject' => $link->email_subject,
            'linkable_type' => $link->linkable_type,
            'linkable_id'   => $link->linkable_id,
            'created_at'    => $link->created_at?->toIso8601String(),
        ])->toJson();
    };
@endphp

<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Flagged emails</h2>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                {{ $emails->count() }}
            </span>
            <a href="{{ route('mail.index') }}" class="text-xs text-brand-500 hover:underline">View all</a>
        </div>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($emails as $email)
            <div
                x-data="emailActions({{ $email->id }}, {{ $linksJson($email) }}, {{ $actionService->senderIsTeamMember($email) ? 'true' : 'false' }})"
                class="flex items-start justify-between gap-3 px-5 py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                        {{ $email->subject }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ $email->sender_name ?? $email->sender_email ?? 'Unknown' }}
                        @if($email->flag_due_date)
                            <span class="mx-1">&middot;</span>
                            <span class="{{ $email->flag_due_date->isPast() ? 'text-red-500' : 'text-amber-600 dark:text-amber-400' }}">
                                Due {{ $email->flag_due_date->format('d M') }}
                            </span>
                        @endif
                    </p>

                    {{-- Linked resource pills --}}
                    <x-tl.email-pills />
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    @if($email->web_link)
                        <a href="{{ $email->web_link }}" target="_blank" rel="noopener noreferrer"
                            class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            title="Open in Outlook" aria-label="Open in Outlook">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </a>
                    @endif

                    <x-tl.email-actions />
                </div>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                No flagged emails.
            </p>
        @endforelse
    </div>
</div>
