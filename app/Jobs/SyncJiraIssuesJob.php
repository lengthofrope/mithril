<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\JiraSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Queued job that syncs Jira Cloud issues for a single user.
 *
 * Fetches issues via three JQL queries and upserts them into the local cache.
 * Auth failures (revoked consent) are logged as warnings without re-queuing;
 * all other failures trigger the retry backoff.
 */
class SyncJiraIssuesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of attempts before the job is considered failed.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Backoff delays in seconds between each retry attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     *
     * @param User $user The user whose Jira issues should be synced.
     */
    public function __construct(private readonly User $user) {}

    /**
     * Execute the job.
     *
     * @param JiraSyncService $syncService The Jira sync service.
     * @return void
     */
    public function handle(JiraSyncService $syncService): void
    {
        try {
            $syncService->syncIssues($this->user);
        } catch (RuntimeException $exception) {
            $this->user->refresh();

            if (!$this->user->hasJiraConnection()) {
                Log::warning('Jira sync skipped — consent revoked.', [
                    'user_id' => $this->user->id,
                    'reason'  => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }
}
