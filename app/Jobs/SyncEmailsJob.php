<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Queued job that syncs Microsoft Graph emails for a single user.
 *
 * Fetches filtered messages based on user preferences and upserts them into
 * the emails table. Auth failures (revoked consent) are logged as warnings
 * without re-queuing; all other failures trigger the retry backoff.
 */
class SyncEmailsJob implements ShouldQueue
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
     * @param User $user The user whose emails should be synced.
     */
    public function __construct(private readonly User $user) {}

    /**
     * Execute the job.
     *
     * @param EmailSyncService $syncService The email sync service.
     * @return void
     */
    public function handle(EmailSyncService $syncService): void
    {
        try {
            $syncService->syncEmails($this->user);
        } catch (RuntimeException $exception) {
            $this->user->refresh();

            if (!$this->user->hasMicrosoftConnection()) {
                Log::warning('Email sync skipped — Microsoft consent revoked.', [
                    'user_id' => $this->user->id,
                    'reason'  => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }
}
