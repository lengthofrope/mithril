<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Agreement;
use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
describe('TeamMember model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(TeamMember::class)))->toBeTrue();
        });

        it('uses the Filterable trait', function (): void {
            expect(in_array(Filterable::class, class_uses_recursive(TeamMember::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(TeamMember::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Alice',
                'role' => 'Developer',
                'email' => 'alice@example.com',
                'notes' => 'Some notes',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 7,
                'next_bila_date' => '2025-06-01',
            ]);

            expect($member->name)->toBe('Alice')
                ->and($member->role)->toBe('Developer')
                ->and($member->email)->toBe('alice@example.com')
                ->and($member->bila_interval_days)->toBe(7);
        });
    });

    describe('enum casts', function (): void {
        it('casts status to MemberStatus enum', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Bob',
                'status' => MemberStatus::Absent,
            ]);

            expect($member->fresh()->status)->toBe(MemberStatus::Absent);
        });

        it('casts next_bila_date to a Carbon date instance', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Carol',
                'next_bila_date' => '2025-06-15',
            ]);

            expect($member->fresh()->next_bila_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Team', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->team())->toBeInstanceOf(BelongsTo::class)
                ->and($member->team->id)->toBe($team->id);
        });

        it('has a hasMany relationship to Task', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->tasks())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tasks', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            Task::create(['title' => 'Task 1', 'team_member_id' => $member->id]);
            Task::create(['title' => 'Task 2', 'team_member_id' => $member->id]);

            expect($member->tasks)->toHaveCount(2);
        });

        it('has a hasMany relationship to FollowUp', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->followUps())->toBeInstanceOf(HasMany::class);
        });

        it('returns related follow-ups', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            FollowUp::create(['team_member_id' => $member->id, 'description' => 'FU 1', 'status' => 'open']);

            expect($member->followUps)->toHaveCount(1);
        });

        it('has a hasMany relationship to Bila', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->bilas())->toBeInstanceOf(HasMany::class);
        });

        it('returns related bilas', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);
            Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-07-01']);

            expect($member->bilas)->toHaveCount(2);
        });

        it('has a hasMany relationship to Agreement', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->agreements())->toBeInstanceOf(HasMany::class);
        });

        it('returns related agreements', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            Agreement::create(['team_member_id' => $member->id, 'description' => 'Agreement 1', 'agreed_date' => '2025-01-01']);

            expect($member->agreements)->toHaveCount(1);
        });

        it('has a hasMany relationship to BilaPrepItem', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->bilaPrepItems())->toBeInstanceOf(HasMany::class);
        });

        it('returns related bila prep items', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            BilaPrepItem::create(['team_member_id' => $member->id, 'content' => 'Prep item 1']);

            expect($member->bilaPrepItems)->toHaveCount(1);
        });

        it('has a hasMany relationship to Note', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            expect($member->notes())->toBeInstanceOf(HasMany::class);
        });

        it('returns related notes', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            Note::create(['title' => 'Note 1', 'content' => 'Content', 'team_member_id' => $member->id]);

            expect($member->notes)->toHaveCount(1);
        });
    });
});
