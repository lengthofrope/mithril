<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DataPruningService;
use Illuminate\Console\Command;

/**
 * Artisan command to prune old completed tasks and follow-ups.
 */
class PruneOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:prune
        {--user= : Prune for a specific user email}
        {--dry-run : Show what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old completed tasks and follow-ups beyond the configured retention period';

    /**
     * Execute the console command.
     *
     * @param DataPruningService $service
     * @return int
     */
    public function handle(DataPruningService $service): int
    {
        $email = $this->option('user');
        $isDryRun = (bool) $this->option('dry-run');

        if ($email) {
            return $this->pruneForEmail($service, $email, $isDryRun);
        }

        return $this->pruneAllConfiguredUsers($service, $isDryRun);
    }

    /**
     * Prune data for a specific user identified by email.
     *
     * @param DataPruningService $service
     * @param string $email
     * @param bool $isDryRun
     * @return int
     */
    private function pruneForEmail(DataPruningService $service, string $email, bool $isDryRun): int
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->prune_after_days === null) {
            $this->info("User [{$email}] has no prune retention period configured. Skipping.");

            return self::SUCCESS;
        }

        $this->pruneUser($service, $user, $isDryRun);

        return self::SUCCESS;
    }

    /**
     * Prune data for all users with a configured retention period.
     *
     * @param DataPruningService $service
     * @param bool $isDryRun
     * @return int
     */
    private function pruneAllConfiguredUsers(DataPruningService $service, bool $isDryRun): int
    {
        $users = User::whereNotNull('prune_after_days')->get();

        if ($users->isEmpty()) {
            $this->info('No users with pruning configured.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->pruneUser($service, $user, $isDryRun);
        }

        return self::SUCCESS;
    }

    /**
     * Execute pruning (or dry-run count) for a single user and output results.
     *
     * @param DataPruningService $service
     * @param User $user
     * @param bool $isDryRun
     * @return void
     */
    private function pruneUser(DataPruningService $service, User $user, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '[DRY RUN] ' : '';

        $result = $isDryRun
            ? $service->countForUser($user)
            : $service->pruneForUser($user);

        $verb = $isDryRun ? 'Would prune' : 'Pruned';

        $this->info(
            "{$prefix}{$verb} {$result->tasksDeleted} task(s) and {$result->followUpsDeleted} follow-up(s) for {$user->email}."
        );
    }
}
