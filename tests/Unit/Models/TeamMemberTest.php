<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Agreement;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Alice',
                'role' => 'Developer',
                'email' => 'alice@example.com',
                'notes' => 'Some notes',
                'status' => MemberStatus::Available,
                'meeting_interval_days' => 7,
                'next_meeting_date' => '2025-06-01',
                'user_id' => $user->id,
            ]);

            expect($member->name)->toBe('Alice')
                ->and($member->role)->toBe('Developer')
                ->and($member->email)->toBe('alice@example.com')
                ->and($member->meeting_interval_days)->toBe(7);
        });
    });

    describe('enum casts', function (): void {
        it('casts status to MemberStatus enum', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Bob',
                'status' => MemberStatus::Absent,
                'user_id' => $user->id,
            ]);

            expect($member->fresh()->status)->toBe(MemberStatus::Absent);
        });

        it('casts next_meeting_date to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Carol',
                'next_meeting_date' => '2025-06-15',
                'user_id' => $user->id,
            ]);

            expect($member->fresh()->next_meeting_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Team', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->team())->toBeInstanceOf(BelongsTo::class)
                ->and($member->team->id)->toBe($team->id);
        });

        it('has a hasMany relationship to Task', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->tasks())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tasks', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            Task::create(['title' => 'Task 1', 'team_member_id' => $member->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Task 2', 'team_member_id' => $member->id, 'user_id' => $user->id]);

            expect($member->tasks)->toHaveCount(2);
        });

        it('has a hasMany relationship to FollowUp', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->followUps())->toBeInstanceOf(HasMany::class);
        });

        it('returns related follow-ups', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            FollowUp::create(['team_member_id' => $member->id, 'description' => 'FU 1', 'status' => 'open', 'user_id' => $user->id]);

            expect($member->followUps)->toHaveCount(1);
        });

        it('has a BelongsToMany relationship to Meeting', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->meetings())->toBeInstanceOf(BelongsToMany::class);
        });

        it('returns related meetings', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $meeting1 = Meeting::factory()->create(['user_id' => $user->id]);
            $meeting2 = Meeting::factory()->create(['user_id' => $user->id]);
            $meeting1->attendees()->attach($member->id);
            $meeting2->attendees()->attach($member->id);

            expect($member->meetings)->toHaveCount(2);
        });

        it('has a hasMany relationship to Agreement', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->agreements())->toBeInstanceOf(HasMany::class);
        });

        it('returns related agreements', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            Agreement::create(['team_member_id' => $member->id, 'description' => 'Agreement 1', 'agreed_date' => '2025-01-01', 'user_id' => $user->id]);

            expect($member->agreements)->toHaveCount(1);
        });

        it('has a hasMany relationship to MeetingPrepItem', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->meetingPrepItems())->toBeInstanceOf(HasMany::class);
        });

        it('returns related meeting prep items', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingPrepItem::create(['team_member_id' => $member->id, 'meeting_id' => $meeting->id, 'content' => 'Prep item 1', 'user_id' => $user->id]);

            expect($member->meetingPrepItems)->toHaveCount(1);
        });

        it('has a hasMany relationship to Note', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            expect($member->notes())->toBeInstanceOf(HasMany::class);
        });

        it('returns related notes', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            Note::create(['title' => 'Note 1', 'content' => 'Content', 'team_member_id' => $member->id, 'user_id' => $user->id]);

            expect($member->notes)->toHaveCount(1);
        });
    });
});
