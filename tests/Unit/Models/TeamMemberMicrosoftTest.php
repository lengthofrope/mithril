<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('TeamMember Microsoft fields', function (): void {
    describe('casts', function (): void {
        it('casts status_source to the StatusSource enum', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'       => $team->id,
                'name'          => 'Alice',
                'user_id'       => $user->id,
                'status_source' => 'microsoft',
            ]);

            expect($member->fresh()->status_source)->toBe(StatusSource::Microsoft);
        });

        it('casts status_source to manual enum case when set to manual', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'       => $team->id,
                'name'          => 'Bob',
                'user_id'       => $user->id,
                'status_source' => 'manual',
            ]);

            expect($member->fresh()->status_source)->toBe(StatusSource::Manual);
        });

        it('casts status_synced_at to a Carbon datetime instance', function (): void {
            $user      = User::factory()->create();
            $team      = Team::factory()->create(['user_id' => $user->id]);
            $syncedAt  = now()->subMinutes(5);
            $member    = TeamMember::create([
                'team_id'          => $team->id,
                'name'             => 'Carol',
                'user_id'          => $user->id,
                'status_synced_at' => $syncedAt,
            ]);

            expect($member->fresh()->status_synced_at)->toBeInstanceOf(Carbon::class);
        });

        it('keeps status_synced_at as null when not set', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name'    => 'Dave',
                'user_id' => $user->id,
            ]);

            expect($member->fresh()->status_synced_at)->toBeNull();
        });
    });

    describe('hasAutoStatus()', function (): void {
        it('returns true when status_source is microsoft', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'       => $team->id,
                'name'          => 'Eve',
                'user_id'       => $user->id,
                'status_source' => StatusSource::Microsoft,
            ]);

            expect($member->hasAutoStatus())->toBeTrue();
        });

        it('returns false when status_source is manual', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'       => $team->id,
                'name'          => 'Frank',
                'user_id'       => $user->id,
                'status_source' => StatusSource::Manual,
            ]);

            expect($member->hasAutoStatus())->toBeFalse();
        });

        it('returns false when status_source defaults (no explicit value set)', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name'    => 'Grace',
                'user_id' => $user->id,
            ]);

            expect($member->hasAutoStatus())->toBeFalse();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of microsoft_email', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'         => $team->id,
                'name'            => 'Heidi',
                'user_id'         => $user->id,
                'microsoft_email' => 'heidi@example.com',
            ]);

            expect($member->microsoft_email)->toBe('heidi@example.com');
        });

        it('allows mass assignment of status_source', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id'       => $team->id,
                'name'          => 'Ivan',
                'user_id'       => $user->id,
                'status_source' => StatusSource::Microsoft,
            ]);

            expect($member->status_source)->toBe(StatusSource::Microsoft);
        });

        it('allows mass assignment of status_synced_at', function (): void {
            $user     = User::factory()->create();
            $team     = Team::factory()->create(['user_id' => $user->id]);
            $syncTime = now();
            $member   = TeamMember::create([
                'team_id'          => $team->id,
                'name'             => 'Julia',
                'user_id'          => $user->id,
                'status_synced_at' => $syncTime,
            ]);

            expect($member->status_synced_at)->not->toBeNull();
        });

        it('stores null for microsoft_email when not provided', function (): void {
            $user   = User::factory()->create();
            $team   = Team::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name'    => 'Karl',
                'user_id' => $user->id,
            ]);

            expect($member->microsoft_email)->toBeNull();
        });
    });
});
