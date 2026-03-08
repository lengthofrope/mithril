<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-08 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('analytics:snapshot command', function (): void {
    it('returns success exit code', function (): void {
        User::factory()->create();

        $this->artisan('analytics:snapshot')
            ->assertExitCode(0);
    });

    it('creates 9 metric rows per user', function (): void {
        $user = User::factory()->create();

        $this->artisan('analytics:snapshot');

        expect(AnalyticsSnapshot::where('user_id', $user->id)->count())->toBe(9);
    });

    it('snapshots all expected metric names', function (): void {
        $user = User::factory()->create();

        $this->artisan('analytics:snapshot');

        $metrics = AnalyticsSnapshot::where('user_id', $user->id)
            ->pluck('metric')
            ->sort()
            ->values()
            ->all();

        expect($metrics)->toBe([
            'follow_ups_status_done',
            'follow_ups_status_open',
            'follow_ups_status_snoozed',
            'follow_ups_total',
            'tasks_status_done',
            'tasks_status_in_progress',
            'tasks_status_open',
            'tasks_status_waiting',
            'tasks_total',
        ]);
    });

    it('counts task statuses correctly', function (): void {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
        Task::factory()->count(2)->create(['user_id' => $user->id, 'status' => TaskStatus::InProgress]);
        Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Waiting]);
        Task::factory()->count(4)->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

        $this->artisan('analytics:snapshot');

        $snapshots = AnalyticsSnapshot::where('user_id', $user->id)
            ->pluck('value', 'metric');

        expect($snapshots['tasks_status_open'])->toBe(3);
        expect($snapshots['tasks_status_in_progress'])->toBe(2);
        expect($snapshots['tasks_status_waiting'])->toBe(1);
        expect($snapshots['tasks_status_done'])->toBe(4);
        expect($snapshots['tasks_total'])->toBe(10);
    });

    it('counts follow-up statuses correctly', function (): void {
        $user = User::factory()->create();
        FollowUp::create(['description' => 'a', 'status' => FollowUpStatus::Open, 'follow_up_date' => now()->addDay(), 'user_id' => $user->id]);
        FollowUp::create(['description' => 'b', 'status' => FollowUpStatus::Open, 'follow_up_date' => now()->addDay(), 'user_id' => $user->id]);
        FollowUp::create(['description' => 'c', 'status' => FollowUpStatus::Snoozed, 'follow_up_date' => now()->addDay(), 'user_id' => $user->id]);
        FollowUp::create(['description' => 'd', 'status' => FollowUpStatus::Done, 'follow_up_date' => now()->addDay(), 'user_id' => $user->id]);

        $this->artisan('analytics:snapshot');

        $snapshots = AnalyticsSnapshot::where('user_id', $user->id)
            ->pluck('value', 'metric');

        expect($snapshots['follow_ups_status_open'])->toBe(2);
        expect($snapshots['follow_ups_status_snoozed'])->toBe(1);
        expect($snapshots['follow_ups_status_done'])->toBe(1);
        expect($snapshots['follow_ups_total'])->toBe(4);
    });

    it('snapshots multiple users independently', function (): void {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Task::factory()->count(5)->create(['user_id' => $userA->id, 'status' => TaskStatus::Open]);
        Task::factory()->count(2)->create(['user_id' => $userB->id, 'status' => TaskStatus::Open]);

        $this->artisan('analytics:snapshot');

        $openA = AnalyticsSnapshot::where('user_id', $userA->id)
            ->where('metric', 'tasks_status_open')
            ->value('value');
        $openB = AnalyticsSnapshot::where('user_id', $userB->id)
            ->where('metric', 'tasks_status_open')
            ->value('value');

        expect($openA)->toBe(5);
        expect($openB)->toBe(2);
    });

    it('uses today as the snapshot date', function (): void {
        $user = User::factory()->create();

        $this->artisan('analytics:snapshot');

        $dates = AnalyticsSnapshot::where('user_id', $user->id)
            ->pluck('snapshot_date')
            ->unique()
            ->map(fn ($d) => $d->toDateString())
            ->all();

        expect($dates)->toBe(['2026-03-08']);
    });

    it('is idempotent when run twice on the same day', function (): void {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);

        $this->artisan('analytics:snapshot');
        $this->artisan('analytics:snapshot');

        expect(AnalyticsSnapshot::where('user_id', $user->id)->count())->toBe(9);

        $openValue = AnalyticsSnapshot::where('user_id', $user->id)
            ->where('metric', 'tasks_status_open')
            ->value('value');

        expect($openValue)->toBe(3);
    });

    it('updates values on re-run if data has changed', function (): void {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);

        $this->artisan('analytics:snapshot');

        $openBefore = AnalyticsSnapshot::where('user_id', $user->id)
            ->where('metric', 'tasks_status_open')
            ->value('value');

        expect($openBefore)->toBe(2);

        Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);

        $this->artisan('analytics:snapshot');

        $openAfter = AnalyticsSnapshot::where('user_id', $user->id)
            ->where('metric', 'tasks_status_open')
            ->value('value');

        expect($openAfter)->toBe(3);
    });

    it('returns zero counts when user has no tasks or follow-ups', function (): void {
        $user = User::factory()->create();

        $this->artisan('analytics:snapshot');

        $snapshots = AnalyticsSnapshot::where('user_id', $user->id)
            ->pluck('value', 'metric');

        foreach ($snapshots as $value) {
            expect($value)->toBe(0);
        }
    });
});
