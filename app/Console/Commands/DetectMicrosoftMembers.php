<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusSource;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Artisan command that checks all manual team members to determine whether
 * their email address resolves to a known Microsoft 365 mailbox.
 *
 * When a match is found the member's status_source is upgraded from manual
 * to microsoft and the microsoft_email field is populated, enabling
 * automatic availability syncing via the existing SyncMemberAvailabilityJob.
 *
 * Global scopes are bypassed because this command runs outside the HTTP
 * request lifecycle and must query all users regardless of session.
 */
class DetectMicrosoftMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'microsoft:detect-members';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check manual team members for O365 mailbox availability and upgrade their status source';

    /**
     * Execute the console command.
     *
     * Finds all manual team members with an email address whose owning user has
     * a Microsoft connection. For each, probes the Graph API to determine whether
     * the email resolves to a known O365 mailbox. Upgrades matching members to
     * microsoft status source so they are included in availability sync.
     *
     * @return int
     */
    public function handle(MicrosoftGraphService $graphService): int
    {
        $connectedUserIds = User::withoutGlobalScopes()
            ->whereNotNull('microsoft_id')
            ->pluck('id');

        $members = TeamMember::withoutGlobalScopes()
            ->where('status_source', StatusSource::Manual->value)
            ->whereNotNull('email')
            ->whereIn('user_id', $connectedUserIds)
            ->with('user')
            ->get();

        $this->info("Checking {$members->count()} manual member(s) with email for O365 compatibility.");

        $upgraded = 0;

        foreach ($members as $member) {
            try {
                $isKnown = $graphService->isKnownMicrosoftUser($member->user, $member->email);
            } catch (RuntimeException $exception) {
                $this->warn("  Skipped {$member->name} ({$member->email}): {$exception->getMessage()}");
                continue;
            }

            if (! $isKnown) {
                $this->line("  {$member->name} ({$member->email}): not found in O365.");
                continue;
            }

            $member->status_source   = StatusSource::Microsoft;
            $member->microsoft_email = $member->email;
            $member->save();

            $upgraded++;
            $this->line("  {$member->name} ({$member->email}): upgraded to Microsoft status source.");
        }

        $this->info("Done. {$upgraded} member(s) upgraded to Microsoft status source.");

        return self::SUCCESS;
    }
}
