<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan command to delete attachments whose parent activity no longer exists.
 */
class CleanOrphanedAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attachments:clean-orphaned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete attachments whose parent activity no longer exists';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $orphans = Attachment::whereNotExists(function ($query): void {
            $query->selectRaw(1)
                ->from('activities')
                ->whereColumn('activities.id', 'attachments.activity_id');
        })->get();

        foreach ($orphans as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->deleteQuietly();
        }

        $count = $orphans->count();

        $this->info("Cleaned {$count} orphaned attachment(s).");

        return self::SUCCESS;
    }
}
