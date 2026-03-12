<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncEmailsJob;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command that dispatches email sync jobs for all Microsoft-connected users.
 */
class SyncEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'microsoft:sync-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from Microsoft Graph for all connected users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $users = User::withoutGlobalScopes()
            ->whereNotNull('microsoft_id')
            ->get();

        $this->info("Dispatching email sync for {$users->count()} connected user(s).");

        foreach ($users as $user) {
            SyncEmailsJob::dispatch($user);
            $this->line("  Queued sync for user #{$user->id}.");
        }

        $this->info('All email sync jobs dispatched.');

        return self::SUCCESS;
    }
}
