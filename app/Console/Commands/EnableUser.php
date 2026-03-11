<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Artisan command to re-enable a previously disabled user account.
 */
class EnableUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:enable {email : The email address of the user to enable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-enable a disabled user account';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->is_active) {
            $this->info("User [{$email}] is already active.");

            return self::SUCCESS;
        }

        $user->update(['is_active' => true]);

        $this->info("User [{$email}] has been re-enabled.");

        return self::SUCCESS;
    }
}
