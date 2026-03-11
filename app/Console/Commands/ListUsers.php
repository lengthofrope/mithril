<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command to list all user accounts with their status.
 */
class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all user accounts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $users = User::orderBy('name')->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Email', 'Active'],
            $users->map(fn (User $user): array => [
                $user->name,
                $user->email,
                $user->is_active ? 'Yes' : 'No',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
