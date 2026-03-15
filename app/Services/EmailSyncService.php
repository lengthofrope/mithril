<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Email;
use App\Models\User;

/**
 * Handles email synchronization from Microsoft Graph to the local cache.
 *
 * Always fetches all inbox emails. Source tags (flagged, categorized, unread)
 * are determined per-message and stored for display filtering/grouping.
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
     * Determine which source tags apply to a message based on its properties.
     *
     * @param array<string, mixed> $graphMessage The normalized message from MicrosoftGraphService.
     * @return array<int, string> Array of matched source type strings.
     */
    public function determineSourcesForMessage(array $graphMessage): array
    {
        $sources = [];

        if ($graphMessage['is_flagged'] ?? false) {
            $sources[] = 'flagged';
        }

        if (!empty($graphMessage['categories'])) {
            $sources[] = 'categorized';
        }

        if (!($graphMessage['is_read'] ?? true)) {
            $sources[] = 'unread';
        }

        return $sources;
    }

    /**
     * Normalize a Graph API message response into an array suitable for upsert.
     *
     * Sanitizes all text fields to valid UTF-8, adds source tags, and truncates
     * body_preview to 500 characters.
     *
     * @param array<string, mixed>  $graphMessage The normalized message from MicrosoftGraphService.
     * @param array<int, string>    $sources      The matched source types for this message.
     * @return array<string, mixed> Array ready for Email model upsert.
     */
    public function normalizeMessage(array $graphMessage, array $sources): array
    {
        $bodyPreview = $this->sanitizeUtf8($graphMessage['body_preview'] ?? null);

        if ($bodyPreview !== null && mb_strlen($bodyPreview) > 500) {
            $bodyPreview = mb_substr($bodyPreview, 0, 500);
        }

        return [
            'microsoft_message_id' => $graphMessage['microsoft_message_id'],
            'subject'              => $this->sanitizeUtf8($graphMessage['subject']),
            'sender_name'          => $this->sanitizeUtf8($graphMessage['sender_name']),
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
     * Strip invalid UTF-8 byte sequences from a string.
     *
     * @param string|null $value The input string to sanitize.
     * @return string|null The sanitized string, or null if input was null.
     */
    private function sanitizeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Sync all inbox emails from Microsoft Graph for the given user.
     *
     * Fetches all inbox messages (up to the configured limit), determines source
     * tags per-message, and upserts into the local cache. Messages no longer in
     * the inbox (and not dismissed) are removed.
     *
     * @param User $user The user to sync emails for.
     * @return void
     */
    public function syncEmails(User $user): void
    {
        $messages = $this->graphService->getMyMessages($user);

        $syncedIds = [];

        foreach ($messages as $message) {
            $sources = $this->determineSourcesForMessage($message);
            $normalized = $this->normalizeMessage($message, $sources);

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
            ->whereNotIn('microsoft_message_id', $syncedIds)
            ->delete();
    }
}
