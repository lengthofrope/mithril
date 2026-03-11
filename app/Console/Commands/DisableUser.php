<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command to disable a user account, preventing login.
 */
class DisableUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:disable {email : The email address of the user to disable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable a user account to prevent login';

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

        if (!$user->is_active) {
            $this->info("User [{$email}] is already disabled.");

            return self::SUCCESS;
        }

        $user->is_active = false;
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info("User [{$email}] has been disabled and their sessions invalidated.");

        return self::SUCCESS;
    }
}
