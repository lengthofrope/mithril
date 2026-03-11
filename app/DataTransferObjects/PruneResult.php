<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Immutable result of a data pruning operation for a single user.
 */
final readonly class PruneResult
{
    /**
     * @param int $tasksDeleted    Number of completed tasks pruned.
     * @param int $followUpsDeleted Number of completed follow-ups pruned.
     */
    public function __construct(
        public int $tasksDeleted,
        public int $followUpsDeleted,
    ) {}
}
