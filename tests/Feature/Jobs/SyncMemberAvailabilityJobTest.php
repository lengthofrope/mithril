<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Jobs\SyncMemberAvailabilityJob;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Carbon;

describe('SyncMemberAvailabilityJob', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    });

    afterEach(function (): void {
        Carbon::setTestNow();
        Mockery::close();
    });

    it('updates member status to InAMeeting when graph returns busy scheduleItem', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-123',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Alice',
            'microsoft_email' => 'alice@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'alice@example.com',
                    'availability' => [
                        ['status' => 'busy', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::InAMeeting);
    });

    it('updates member status to Absent when graph returns oof scheduleItem', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-oof',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Bob',
            'microsoft_email' => 'bob@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'bob@example.com',
                    'availability' => [
                        ['status' => 'oof', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::Absent);
    });

    it('updates member status to Available when graph returns free scheduleItem', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-free',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Carol',
            'microsoft_email' => 'carol@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Absent,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'carol@example.com',
                    'availability' => [
                        ['status' => 'free', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::Available);
    });

    it('updates member status to PartiallyAvailable when graph returns tentative scheduleItem', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-tentative',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Tentative Tina',
            'microsoft_email' => 'tina@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'tina@example.com',
                    'availability' => [
                        ['status' => 'tentative', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::PartiallyAvailable);
    });

    it('updates member status to WorkingElsewhere when graph returns workingElsewhere scheduleItem', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-wfh',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Remote Rick',
            'microsoft_email' => 'rick@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'rick@example.com',
                    'availability' => [
                        ['status' => 'workingElsewhere', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::WorkingElsewhere);
    });

    it('skips members without a microsoft_email', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-skip-no-email',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Dave',
            'microsoft_email' => null,
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('getScheduleAvailability');
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);
    });

    it('skips members with manual status_source', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-skip-manual',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Eve',
            'microsoft_email' => 'eve@example.com',
            'status_source'   => StatusSource::Manual,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('getScheduleAvailability');
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::Available);
    });

    it('handles an empty scheduleItems response gracefully without updating the member', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-empty',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Frank',
            'microsoft_email' => 'frank@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'frank@example.com',
                    'availability' => [],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status)->toBe(MemberStatus::Available);
    });

    it('updates status_synced_at to the current timestamp after a successful sync', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-synced-at',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::create([
            'user_id'          => $user->id,
            'team_id'          => $team->id,
            'name'             => 'Grace',
            'microsoft_email'  => 'grace@example.com',
            'status_source'    => StatusSource::Microsoft,
            'status'           => MemberStatus::Available,
            'status_synced_at' => null,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andReturn(collect([
                [
                    'email'        => 'grace@example.com',
                    'availability' => [
                        ['status' => 'busy', 'start' => [], 'end' => []],
                    ],
                ],
            ]));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        (new SyncMemberAvailabilityJob($user))->handle($mock);

        expect($member->fresh()->status_synced_at)->not->toBeNull()
            ->and($member->fresh()->status_synced_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('does not re-throw when auth fails and the user no longer has a microsoft connection', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => null,
            'microsoft_access_token'     => null,
            'microsoft_refresh_token'    => null,
            'microsoft_token_expires_at' => null,
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        TeamMember::withoutGlobalScopes()->where('user_id', $user->id)->delete();
        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Heidi',
            'microsoft_email' => 'heidi@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andThrow(new RuntimeException('Consent revoked'));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        expect(fn () => (new SyncMemberAvailabilityJob($user))->handle($mock))
            ->not->toThrow(RuntimeException::class);
    });

    it('re-throws when auth fails and the user still has a microsoft connection', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-still-connected',
            'microsoft_access_token'     => 'token',
            'microsoft_refresh_token'    => 'refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $team   = Team::factory()->create(['user_id' => $user->id]);
        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Ivan',
            'microsoft_email' => 'ivan@example.com',
            'status_source'   => StatusSource::Microsoft,
            'status'          => MemberStatus::Available,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('getScheduleAvailability')
            ->once()
            ->andThrow(new RuntimeException('Transient Graph failure'));
        $this->app->instance(MicrosoftGraphService::class, $mock);

        expect(fn () => (new SyncMemberAvailabilityJob($user))->handle($mock))
            ->toThrow(RuntimeException::class, 'Transient Graph failure');
    });
});
