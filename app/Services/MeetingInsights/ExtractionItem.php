<?php

declare(strict_types=1);

namespace App\Services\MeetingInsights;

/**
 * Value object representing a single extracted item from a meeting transcription.
 */
class ExtractionItem
{
    /**
     * Create the extraction item.
     *
     * @param string      $type       One of: task, follow_up, agreement, decision.
     * @param string      $content    Description of the extracted item.
     * @param int|null    $assigneeId Suggested team member ID.
     * @param string|null $priority   Suggested priority (urgent, high, normal, low).
     * @param string|null $deadline   Suggested deadline (Y-m-d format).
     */
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly ?int $assigneeId = null,
        public readonly ?string $priority = null,
        public readonly ?string $deadline = null,
    ) {}

    /**
     * Create from an associative array (from AI JSON response).
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'task',
            content: $data['content'] ?? '',
            assigneeId: isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            priority: $data['priority'] ?? null,
            deadline: $data['deadline'] ?? null,
        );
    }
}
