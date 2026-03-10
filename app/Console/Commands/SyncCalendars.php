<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCalendarEventsJob;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command that dispatches calendar sync jobs for all Microsoft-connected users.
 *
 * Each eligible user is dispatched as an independent queued job so that a single
 * user's token failure does not block or fail the entire batch. Global scopes are
 * bypassed because this command runs outside the HTTP request lifecycle and must
 * query all users regardless of the authenticated session.
 */
class SyncCalendars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'microsoft:sync-calendars';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync calendar events from Microsoft Graph for all connected users';

    /**
     * Execute the console command.
     *
     * Finds all users with an active Microsoft connection and dispatches a
     * SyncCalendarEventsJob for each one.
     *
     * @return int
     */
    public function handle(): int
    {
        $users = User::withoutGlobalScopes()
            ->whereNotNull('microsoft_id')
            ->get();

        $this->info("Dispatching calendar sync for {$users->count()} connected user(s).");

        foreach ($users as $user) {
            SyncCalendarEventsJob::dispatch($user);
            $this->line("  Queued sync for user #{$user->id}.");
        }

        $this->info('All calendar sync jobs dispatched.');

        return self::SUCCESS;
    }
}
