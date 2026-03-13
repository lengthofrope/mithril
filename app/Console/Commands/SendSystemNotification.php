<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationVariant;
use App\Models\SystemNotification;
use Illuminate\Console\Command;

/**
 * Artisan command to create a broadcast system notification for all users.
 */
class SendSystemNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send
        {--title= : The notification title}
        {--message= : The notification message body}
        {--variant=info : Visual variant (info, warning, success, error)}
        {--link-url= : Optional URL for a call-to-action link}
        {--link-text= : Display text for the link}
        {--expires-at= : Optional expiry datetime (Y-m-d H:i:s)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a dismissable notification to all users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = $this->option('title');
        $message = $this->option('message');
        $variantValue = $this->option('variant');

        if (empty($title)) {
            $this->error('The --title option is required.');

            return self::FAILURE;
        }

        if (empty($message)) {
            $this->error('The --message option is required.');

            return self::FAILURE;
        }

        $variant = NotificationVariant::tryFrom($variantValue);

        if ($variant === null) {
            $this->error("Invalid variant [{$variantValue}]. Use: info, warning, success, error.");

            return self::FAILURE;
        }

        $expiresAt = $this->option('expires-at');

        SystemNotification::create([
            'title'      => $title,
            'message'    => $message,
            'variant'    => $variant,
            'link_url'   => $this->option('link-url'),
            'link_text'  => $this->option('link-text'),
            'is_active'  => true,
            'expires_at' => $expiresAt,
        ]);

        $this->info("System notification [{$title}] created successfully.");

        return self::SUCCESS;
    }
}
