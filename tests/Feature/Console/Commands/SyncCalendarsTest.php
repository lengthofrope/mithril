<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

describe('microsoft:sync-calendars command', function (): void {
    it('dispatches a sync job for each user with a Microsoft connection', function (): void {
        Queue::fake();

        User::factory()->create(['microsoft_id' => 'ms-user-1']);
        User::factory()->create(['microsoft_id' => 'ms-user-2']);
        User::factory()->create(['microsoft_id' => 'ms-user-3']);

        $this->artisan('microsoft:sync-calendars')
            ->assertExitCode(0);

        Queue::assertPushed(SyncCalendarEventsJob::class, 3);
    });

    it('skips users without a microsoft_id', function (): void {
        Queue::fake();

        User::factory()->create(['microsoft_id' => 'ms-connected']);
        User::factory()->create(['microsoft_id' => null]);
        User::factory()->create(['microsoft_id' => null]);

        $this->artisan('microsoft:sync-calendars')
            ->assertExitCode(0);

        Queue::assertPushed(SyncCalendarEventsJob::class, 1);
    });

    it('completes successfully with no connected users and dispatches no jobs', function (): void {
        Queue::fake();

        User::factory()->create(['microsoft_id' => null]);
        User::factory()->create(['microsoft_id' => null]);

        $this->artisan('microsoft:sync-calendars')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });

    it('dispatches sync jobs for the correct users', function (): void {
        Queue::fake();

        $connectedUser    = User::factory()->create(['microsoft_id' => 'ms-abc']);
        $disconnectedUser = User::factory()->create(['microsoft_id' => null]);

        $this->artisan('microsoft:sync-calendars');

        Queue::assertPushed(SyncCalendarEventsJob::class, function (SyncCalendarEventsJob $job) use ($connectedUser): bool {
            $user = (new ReflectionClass($job))->getProperty('user')->getValue($job);

            return $user->id === $connectedUser->id;
        });
    });

    it('returns success exit code when there are no users at all', function (): void {
        Queue::fake();

        $this->artisan('microsoft:sync-calendars')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });
});
