<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Email;
use App\Models\User;

/**
 * Handles email synchronization from Microsoft Graph to the local cache.
 */
class EmailSyncService
{
    /**
     * Create a new EmailSyncService instance.
     */
    public function __construct(
        private readonly MicrosoftGraphService $graphService,
    ) {}

    /**
     * Build the OData filter string based on user preferences.
     *
     * Combines active sources with OR logic.
     *
     * @param User $user The user whose preferences determine the filter.
     * @return string OData filter string, or empty string if no sources enabled.
     */
    public function buildFilter(User $user): string
    {
        $filters = [];

        if ($user->email_source_flagged) {
            $filters[] = "flag/flagStatus eq 'flagged'";
        }

        if ($user->email_source_categorized) {
            $categoryName = $user->email_source_category_name ?? 'Mithril';
            $filters[] = "categories/any(c:c eq '{$categoryName}')";
        }

        if ($user->email_source_unread) {
            $filters[] = 'isRead eq false';
        }

        if (empty($filters)) {
            return '';
        }

        return implode(' or ', $filters);
    }

    /**
     * Normalize a Graph API message response into an array suitable for upsert.
     *
     * Adds sources and is_dismissed fields. Truncates body_preview to 500 chars.
     *
     * @param array<string, mixed>  $graphMessage The normalized message from MicrosoftGraphService.
     * @param array<int, string>    $sources      The matched source types for this message.
     * @return array<string, mixed> Array ready for Email model upsert.
     */
    public function normalizeMessage(array $graphMessage, array $sources): array
    {
        $bodyPreview = $graphMessage['body_preview'] ?? null;

        if ($bodyPreview !== null && strlen($bodyPreview) > 500) {
            $bodyPreview = substr($bodyPreview, 0, 500);
        }

        return [
            'microsoft_message_id' => $graphMessage['microsoft_message_id'],
            'subject'              => $graphMessage['subject'],
            'sender_name'          => $graphMessage['sender_name'],
            'sender_email'         => $graphMessage['sender_email'],
            'received_at'          => $graphMessage['received_at'],
            'body_preview'         => $bodyPreview,
            'is_read'              => $graphMessage['is_read'],
            'is_flagged'           => $graphMessage['is_flagged'],
            'flag_due_date'        => $graphMessage['flag_due_date'],
            'categories'           => $graphMessage['categories'],
            'importance'           => $graphMessage['importance'],
            'has_attachments'      => $graphMessage['has_attachments'],
            'web_link'             => $graphMessage['web_link'],
            'sources'              => $sources,
            'is_dismissed'         => false,
            'synced_at'            => now(),
        ];
    }

    /**
     * Sync emails from Microsoft Graph for the given user.
     *
     * Upserts into the emails table, removes emails that no longer match filters.
     * Dismissed emails are never removed by sync.
     *
     * @param User $user The user to sync emails for.
     * @return void
     */
    public function syncEmails(User $user): void
    {
        $filter = $this->buildFilter($user);

        if ($filter === '') {
            return;
        }

        $messages = $this->graphService->getMyMessages($user, $filter);

        $activeSources = $this->getActiveSources($user);
        $syncedIds = [];

        foreach ($messages as $message) {
            $normalized = $this->normalizeMessage($message, $activeSources);

            Email::withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'user_id'              => $user->id,
                        'microsoft_message_id' => $normalized['microsoft_message_id'],
                    ],
                    $normalized,
                );

            $syncedIds[] = $normalized['microsoft_message_id'];
        }

        Email::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_dismissed', false)
            ->whereNotIn('microsoft_message_id', $syncedIds)
            ->delete();
    }

    /**
     * Get the list of active source names for the user.
     *
     * @param User $user The user to check sources for.
     * @return array<int, string> Array of active source type strings.
     */
    private function getActiveSources(User $user): array
    {
        $sources = [];

        if ($user->email_source_flagged) {
            $sources[] = 'flagged';
        }

        if ($user->email_source_categorized) {
            $sources[] = 'categorized';
        }

        if ($user->email_source_unread) {
            $sources[] = 'unread';
        }

        return $sources;
    }
}
