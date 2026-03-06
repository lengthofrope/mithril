<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

describe('FollowUp model', function (): void {
    describe('traits', function (): void {
        it('uses the Filterable trait', function (): void {
            expect(in_array(Filterable::class, class_uses_recursive(FollowUp::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(FollowUp::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'description' => 'Follow up on deliverable',
                'waiting_on' => 'John',
                'follow_up_date' => '2025-06-15',
                'snoozed_until' => '2025-06-10',
                'status' => FollowUpStatus::Snoozed,
                'user_id' => $user->id,
            ]);

            expect($followUp->description)->toBe('Follow up on deliverable')
                ->and($followUp->waiting_on)->toBe('John');
        });
    });

    describe('enum casts', function (): void {
        it('casts status to FollowUpStatus enum', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'description' => 'Done item',
                'status' => FollowUpStatus::Done,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
        });

        it('casts follow_up_date to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'description' => 'Dated item',
                'follow_up_date' => '2025-07-01',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->follow_up_date)->toBeInstanceOf(Carbon::class);
        });

        it('casts snoozed_until to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'description' => 'Snoozed item',
                'snoozed_until' => '2025-07-05',
                'status' => FollowUpStatus::Snoozed,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->snoozed_until)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Task', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Parent task', 'user_id' => $user->id]);
            $followUp = FollowUp::create([
                'task_id' => $task->id,
                'description' => 'Follow up',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->task())->toBeInstanceOf(BelongsTo::class)
                ->and($followUp->task->id)->toBe($task->id);
        });

        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $followUp = FollowUp::create([
                'team_member_id' => $member->id,
                'description' => 'Follow up',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($followUp->teamMember->id)->toBe($member->id);
        });

        it('allows null task_id and team_member_id', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'description' => 'Standalone follow up',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->task)->toBeNull()
                ->and($followUp->teamMember)->toBeNull();
        });
    });

    describe('scopes', function (): void {
        it('overdue scope returns past non-done follow-ups', function (): void {
            $user = User::factory()->create();
            FollowUp::create(['description' => 'Past', 'follow_up_date' => Carbon::yesterday(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['description' => 'Future', 'follow_up_date' => Carbon::tomorrow(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::overdue()->count())->toBe(1);
        });

        it('dueToday scope returns today non-done follow-ups', function (): void {
            $user = User::factory()->create();
            FollowUp::create(['description' => 'Today', 'follow_up_date' => Carbon::today(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['description' => 'Tomorrow', 'follow_up_date' => Carbon::tomorrow(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::dueToday()->count())->toBe(1);
        });

        it('dueThisWeek scope returns this-week non-done follow-ups after today', function (): void {
            $user = User::factory()->create();
            $endOfWeek = Carbon::now()->endOfWeek();
            $todayIsEndOfWeek = Carbon::today()->isSameDay($endOfWeek);

            FollowUp::create(['description' => 'This week', 'follow_up_date' => $endOfWeek, 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['description' => 'Today', 'follow_up_date' => Carbon::today(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            // When today is the last day of the week, no date after today falls within this week
            $expected = $todayIsEndOfWeek ? 0 : 1;
            expect(FollowUp::dueThisWeek()->count())->toBe($expected);
        });

        it('upcoming scope returns follow-ups after end of week', function (): void {
            $user = User::factory()->create();
            $afterWeek = Carbon::now()->endOfWeek()->addDay();
            FollowUp::create(['description' => 'Upcoming', 'follow_up_date' => $afterWeek, 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['description' => 'This week', 'follow_up_date' => Carbon::now()->endOfWeek(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::upcoming()->count())->toBe(1);
        });
    });
});
