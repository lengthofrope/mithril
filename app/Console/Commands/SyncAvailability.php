<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusSource;
use App\Jobs\SyncMemberAvailabilityJob;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command that dispatches availability sync jobs for all qualifying users.
 *
 * A user qualifies when they have a Microsoft connection AND at least one team
 * member with status_source set to Microsoft. Global scopes are bypassed because
 * this command runs outside the HTTP request lifecycle and must query all users
 * regardless of the authenticated session. Each eligible user is dispatched as
 * an independent queued job so that a single user's token failure does not block
 * or fail the entire batch.
 */
class SyncAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'microsoft:sync-availability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync team member availability from Microsoft Graph for all connected users';

    /**
     * Execute the console command.
     *
     * Finds all Microsoft-connected users that have at least one team member
     * with a Microsoft-sourced status and dispatches a SyncMemberAvailabilityJob
     * for each qualifying user.
     *
     * @return int
     */
    public function handle(): int
    {
        $userIds = TeamMember::withoutGlobalScopes()
            ->where('status_source', StatusSource::Microsoft->value)
            ->whereNotNull('microsoft_email')
            ->distinct()
            ->pluck('user_id');

        $users = User::withoutGlobalScopes()
            ->whereNotNull('microsoft_id')
            ->whereIn('id', $userIds)
            ->get();

        $this->info("Dispatching availability sync for {$users->count()} qualifying user(s).");

        foreach ($users as $user) {
            SyncMemberAvailabilityJob::dispatch($user);
            $this->line("  Queued availability sync for user #{$user->id}.");
        }

        $this->info('All availability sync jobs dispatched.');

        return self::SUCCESS;
    }
}
