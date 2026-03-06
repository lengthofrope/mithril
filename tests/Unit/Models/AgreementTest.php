<?php

declare(strict_types=1);

use App\Models\Agreement;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

describe('Agreement model', function (): void {
    describe('traits', function (): void {
        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(Agreement::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            $agreement = Agreement::create([
                'team_member_id' => $member->id,
                'description' => 'Will deliver report by Friday',
                'agreed_date' => '2025-05-01',
                'follow_up_date' => '2025-05-10',
                'user_id' => $user->id,
            ]);

            expect($agreement->description)->toBe('Will deliver report by Friday');
        });
    });

    describe('casts', function (): void {
        it('casts agreed_date to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $agreement = Agreement::create([
                'team_member_id' => $member->id,
                'description' => 'Agreement',
                'agreed_date' => '2025-01-15',
                'user_id' => $user->id,
            ]);

            expect($agreement->fresh()->agreed_date)->toBeInstanceOf(Carbon::class);
        });

        it('casts follow_up_date to a Carbon date instance when set', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $agreement = Agreement::create([
                'team_member_id' => $member->id,
                'description' => 'Agreement',
                'agreed_date' => '2025-01-15',
                'follow_up_date' => '2025-02-01',
                'user_id' => $user->id,
            ]);

            expect($agreement->fresh()->follow_up_date)->toBeInstanceOf(Carbon::class);
        });

        it('returns null for follow_up_date when not set', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $agreement = Agreement::create([
                'team_member_id' => $member->id,
                'description' => 'Agreement',
                'agreed_date' => '2025-01-15',
                'user_id' => $user->id,
            ]);

            expect($agreement->fresh()->follow_up_date)->toBeNull();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $agreement = Agreement::create([
                'team_member_id' => $member->id,
                'description' => 'Agreement',
                'agreed_date' => '2025-01-15',
                'user_id' => $user->id,
            ]);

            expect($agreement->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($agreement->teamMember->id)->toBe($member->id);
        });
    });
});
