<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncJiraIssuesJob;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command that dispatches Jira issue sync jobs for all Jira-connected users.
 */
class SyncJiraIssues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:sync-issues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Jira issues for all connected users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $users = User::withoutGlobalScopes()
            ->whereNotNull('jira_cloud_id')
            ->get();

        $this->info("Dispatching Jira sync for {$users->count()} connected user(s).");

        foreach ($users as $user) {
            SyncJiraIssuesJob::dispatch($user);
            $this->line("  Queued sync for user #{$user->id}.");
        }

        $this->info('All Jira sync jobs dispatched.');

        return self::SUCCESS;
    }
}
