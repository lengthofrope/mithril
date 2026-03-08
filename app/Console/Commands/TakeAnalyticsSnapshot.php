<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Artisan command that records a daily analytics snapshot for every user.
 *
 * For each user, the command counts current task and follow-up states and
 * upserts the resulting metrics into the analytics_snapshots table. Running
 * the command multiple times on the same day is safe — the unique index on
 * (user_id, metric, snapshot_date) makes each run idempotent.
 */
class TakeAnalyticsSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record daily analytics snapshot for all users';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $snapshotDate = Carbon::today()->toDateString();
        $users        = User::all();

        $this->info("Taking analytics snapshot for {$users->count()} user(s) on {$snapshotDate}.");

        foreach ($users as $user) {
            $this->snapshotForUser($user->id, $snapshotDate);
        }

        $this->info('Analytics snapshot completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Aggregate metrics for a single user and upsert them into analytics_snapshots.
     *
     * Global scopes are bypassed intentionally here, because this command
     * operates outside the HTTP request lifecycle and must query all users'
     * data without an authenticated session.
     *
     * @param int    $userId       The user to snapshot.
     * @param string $snapshotDate ISO date string (Y-m-d) for the snapshot row.
     * @return void
     */
    private function snapshotForUser(int $userId, string $snapshotDate): void
    {
        $taskBase     = Task::withoutGlobalScopes()->where('user_id', $userId);
        $followUpBase = FollowUp::withoutGlobalScopes()->where('user_id', $userId);

        $rows = [
            ['metric' => 'tasks_status_open',           'value' => (clone $taskBase)->where('status', TaskStatus::Open->value)->count()],
            ['metric' => 'tasks_status_in_progress',    'value' => (clone $taskBase)->where('status', TaskStatus::InProgress->value)->count()],
            ['metric' => 'tasks_status_waiting',        'value' => (clone $taskBase)->where('status', TaskStatus::Waiting->value)->count()],
            ['metric' => 'tasks_status_done',           'value' => (clone $taskBase)->where('status', TaskStatus::Done->value)->count()],
            ['metric' => 'tasks_total',                 'value' => (clone $taskBase)->count()],
            ['metric' => 'follow_ups_status_open',      'value' => (clone $followUpBase)->where('status', FollowUpStatus::Open->value)->count()],
            ['metric' => 'follow_ups_status_snoozed',   'value' => (clone $followUpBase)->where('status', FollowUpStatus::Snoozed->value)->count()],
            ['metric' => 'follow_ups_status_done',      'value' => (clone $followUpBase)->where('status', FollowUpStatus::Done->value)->count()],
            ['metric' => 'follow_ups_total',            'value' => (clone $followUpBase)->count()],
        ];

        $upsertRows = array_map(
            fn (array $row): array => array_merge($row, [
                'user_id'       => $userId,
                'snapshot_date' => $snapshotDate,
            ]),
            $rows,
        );

        AnalyticsSnapshot::upsert(
            $upsertRows,
            uniqueBy: ['user_id', 'metric', 'snapshot_date'],
            update: ['value'],
        );

        $this->line("  Snapshotted user #{$userId} — " . count($rows) . ' metrics.');
    }
}
