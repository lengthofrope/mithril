<?php

declare(strict_types=1);

use App\Enums\StatusSource;
use App\Jobs\SyncMemberAvailabilityJob;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

describe('microsoft:sync-availability command', function (): void {
    it('dispatches a job for each user that has microsoft-sourced members', function (): void {
        Queue::fake();

        $userA = User::factory()->create(['microsoft_id' => 'ms-user-a']);
        $userB = User::factory()->create(['microsoft_id' => 'ms-user-b']);

        $teamA = Team::factory()->create(['user_id' => $userA->id]);
        $teamB = Team::factory()->create(['user_id' => $userB->id]);

        TeamMember::create([
            'user_id'         => $userA->id,
            'team_id'         => $teamA->id,
            'name'            => 'Alice',
            'microsoft_email' => 'alice@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        TeamMember::create([
            'user_id'         => $userB->id,
            'team_id'         => $teamB->id,
            'name'            => 'Bob',
            'microsoft_email' => 'bob@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertPushed(SyncMemberAvailabilityJob::class, 2);
    });

    it('skips users without a microsoft_id even when they have microsoft-sourced members', function (): void {
        Queue::fake();

        $connected    = User::factory()->create(['microsoft_id' => 'ms-connected']);
        $disconnected = User::factory()->create(['microsoft_id' => null]);

        $teamC = Team::factory()->create(['user_id' => $connected->id]);
        $teamD = Team::factory()->create(['user_id' => $disconnected->id]);

        TeamMember::create([
            'user_id'         => $connected->id,
            'team_id'         => $teamC->id,
            'name'            => 'Carol',
            'microsoft_email' => 'carol@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        TeamMember::create([
            'user_id'         => $disconnected->id,
            'team_id'         => $teamD->id,
            'name'            => 'Dave',
            'microsoft_email' => 'dave@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertPushed(SyncMemberAvailabilityJob::class, 1);
    });

    it('skips users whose members all have manual status_source', function (): void {
        Queue::fake();

        $user = User::factory()->create(['microsoft_id' => 'ms-manual-only']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Eve',
            'microsoft_email' => 'eve@example.com',
            'status_source'   => StatusSource::Manual,
        ]);

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });

    it('dispatches a job for the correct user', function (): void {
        Queue::fake();

        $targetUser   = User::factory()->create(['microsoft_id' => 'ms-target']);
        $unrelatedUser = User::factory()->create(['microsoft_id' => null]);

        $team = Team::factory()->create(['user_id' => $targetUser->id]);

        TeamMember::create([
            'user_id'         => $targetUser->id,
            'team_id'         => $team->id,
            'name'            => 'Frank',
            'microsoft_email' => 'frank@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        $this->artisan('microsoft:sync-availability');

        Queue::assertPushed(
            SyncMemberAvailabilityJob::class,
            function (SyncMemberAvailabilityJob $job) use ($targetUser): bool {
                $user = (new ReflectionClass($job))->getProperty('user')->getValue($job);

                return $user->id === $targetUser->id;
            }
        );
    });

    it('completes successfully and dispatches no jobs when there are no qualifying users', function (): void {
        Queue::fake();

        User::factory()->create(['microsoft_id' => null]);

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });

    it('completes successfully and dispatches no jobs when there are no users at all', function (): void {
        Queue::fake();

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });

    it('skips members without a microsoft_email even when status_source is microsoft', function (): void {
        Queue::fake();

        $user = User::factory()->create(['microsoft_id' => 'ms-no-email']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Grace',
            'microsoft_email' => null,
            'status_source'   => StatusSource::Microsoft,
        ]);

        $this->artisan('microsoft:sync-availability')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    });
});
