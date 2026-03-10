<?php

declare(strict_types=1);

use App\Enums\StatusSource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MicrosoftGraphService;

describe('microsoft:detect-members command', function (): void {
    it('upgrades a manual member to microsoft when their email is a known O365 user', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-detect']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        $member = TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'Alice',
            'email'         => 'alice@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), 'alice@example.com')
            ->once()
            ->andReturn(true);

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);

        $member->refresh();
        expect($member->status_source)->toBe(StatusSource::Microsoft);
        expect($member->microsoft_email)->toBe('alice@example.com');
    });

    it('does not upgrade a manual member when their email is not a known O365 user', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-detect-no']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        $member = TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'Bob',
            'email'         => 'bob@gmail.com',
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), 'bob@gmail.com')
            ->once()
            ->andReturn(false);

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);

        $member->refresh();
        expect($member->status_source)->toBe(StatusSource::Manual);
        expect($member->microsoft_email)->toBeNull();
    });

    it('skips manual members without an email address', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-detect-skip']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'NoEmail',
            'email'         => null,
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('isKnownMicrosoftUser');

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);
    });

    it('skips members whose user has no microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'Disconnected',
            'email'         => 'disc@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('isKnownMicrosoftUser');

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);
    });

    it('skips members that are already microsoft-sourced', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-already']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamMember::create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'name'            => 'Already',
            'email'           => 'already@example.com',
            'microsoft_email' => 'already@example.com',
            'status_source'   => StatusSource::Microsoft,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('isKnownMicrosoftUser');

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);
    });

    it('processes multiple members across different users', function (): void {
        $userA = User::factory()->create(['microsoft_id' => 'ms-multi-a']);
        $userB = User::factory()->create(['microsoft_id' => 'ms-multi-b']);

        $teamA = Team::factory()->create(['user_id' => $userA->id]);
        $teamB = Team::factory()->create(['user_id' => $userB->id]);

        $memberA = TeamMember::create([
            'user_id'       => $userA->id,
            'team_id'       => $teamA->id,
            'name'          => 'MemberA',
            'email'         => 'a@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $memberB = TeamMember::create([
            'user_id'       => $userB->id,
            'team_id'       => $teamB->id,
            'name'          => 'MemberB',
            'email'         => 'b@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $userA->id), 'a@example.com')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $userB->id), 'b@example.com')
            ->once()
            ->andReturn(false);

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);

        $memberA->refresh();
        expect($memberA->status_source)->toBe(StatusSource::Microsoft);
        expect($memberA->microsoft_email)->toBe('a@example.com');

        $memberB->refresh();
        expect($memberB->status_source)->toBe(StatusSource::Manual);
        expect($memberB->microsoft_email)->toBeNull();
    });

    it('handles graph API errors gracefully and continues with remaining members', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-error']);
        $team = Team::factory()->create(['user_id' => $user->id]);

        $memberOk = TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'OkMember',
            'email'         => 'ok@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $memberFail = TeamMember::create([
            'user_id'       => $user->id,
            'team_id'       => $team->id,
            'name'          => 'FailMember',
            'email'         => 'fail@example.com',
            'status_source' => StatusSource::Manual,
        ]);

        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), 'fail@example.com')
            ->once()
            ->andThrow(new RuntimeException('Graph API error'));
        $mock->shouldReceive('isKnownMicrosoftUser')
            ->with(Mockery::on(fn ($u) => $u->id === $user->id), 'ok@example.com')
            ->once()
            ->andReturn(true);

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);

        $memberOk->refresh();
        expect($memberOk->status_source)->toBe(StatusSource::Microsoft);

        $memberFail->refresh();
        expect($memberFail->status_source)->toBe(StatusSource::Manual);
    });

    it('completes successfully when there are no manual members', function (): void {
        $mock = Mockery::mock(MicrosoftGraphService::class);
        $mock->shouldNotReceive('isKnownMicrosoftUser');

        $this->app->instance(MicrosoftGraphService::class, $mock);

        $this->artisan('microsoft:detect-members')
            ->assertExitCode(0);
    });
});
