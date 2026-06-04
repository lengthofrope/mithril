<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
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
                'title' => 'Follow up on deliverable',
                'waiting_on' => 'John',
                'follow_up_date' => '2025-06-15',
                'snoozed_until' => '2025-06-10',
                'status' => FollowUpStatus::Snoozed,
                'user_id' => $user->id,
            ]);

            expect($followUp->title)->toBe('Follow up on deliverable')
                ->and($followUp->waiting_on)->toBe('John');
        });
    });

    describe('enum casts', function (): void {
        it('casts status to FollowUpStatus enum', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'title' => 'Done item',
                'status' => FollowUpStatus::Done,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->status)->toBe(FollowUpStatus::Done);
        });

        it('casts follow_up_date to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'title' => 'Dated item',
                'follow_up_date' => '2025-07-01',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->follow_up_date)->toBeInstanceOf(Carbon::class);
        });

        it('casts snoozed_until to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'title' => 'Snoozed item',
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
                'title' => 'Follow up',
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
                'title' => 'Follow up',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($followUp->teamMember->id)->toBe($member->id);
        });

        it('allows null task_id and team_member_id', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::create([
                'title' => 'Standalone follow up',
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
            FollowUp::create(['title' => 'Past', 'follow_up_date' => Carbon::yesterday(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['title' => 'Future', 'follow_up_date' => Carbon::tomorrow(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::overdue()->count())->toBe(1);
        });

        it('dueToday scope returns today non-done follow-ups', function (): void {
            $user = User::factory()->create();
            FollowUp::create(['title' => 'Today', 'follow_up_date' => Carbon::today(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['title' => 'Tomorrow', 'follow_up_date' => Carbon::tomorrow(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::dueToday()->count())->toBe(1);
        });

        it('dueThisWeek scope returns this-week non-done follow-ups after today', function (): void {
            $user = User::factory()->create();
            $endOfWeek = Carbon::now()->endOfWeek();
            $todayIsEndOfWeek = Carbon::today()->isSameDay($endOfWeek);

            FollowUp::create(['title' => 'This week', 'follow_up_date' => $endOfWeek, 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['title' => 'Today', 'follow_up_date' => Carbon::today(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            // When today is the last day of the week, no date after today falls within this week
            $expected = $todayIsEndOfWeek ? 0 : 1;
            expect(FollowUp::dueThisWeek()->count())->toBe($expected);
        });

        it('upcoming scope returns follow-ups after end of week', function (): void {
            $user = User::factory()->create();
            $afterWeek = Carbon::now()->endOfWeek()->addDay();
            FollowUp::create(['title' => 'Upcoming', 'follow_up_date' => $afterWeek, 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['title' => 'This week', 'follow_up_date' => Carbon::now()->endOfWeek(), 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect(FollowUp::upcoming()->count())->toBe(1);
        });

        it('priorityOrdered scope orders urgent, high, normal, low', function (): void {
            $user = User::factory()->create();
            $date = Carbon::parse('2026-06-15');

            FollowUp::factory()->create(['user_id' => $user->id, 'title' => 'Low item', 'priority' => Priority::Low, 'follow_up_date' => $date]);
            FollowUp::factory()->create(['user_id' => $user->id, 'title' => 'Urgent item', 'priority' => Priority::Urgent, 'follow_up_date' => $date]);
            FollowUp::factory()->create(['user_id' => $user->id, 'title' => 'Normal item', 'priority' => Priority::Normal, 'follow_up_date' => $date]);
            FollowUp::factory()->create(['user_id' => $user->id, 'title' => 'High item', 'priority' => Priority::High, 'follow_up_date' => $date]);

            $titles = FollowUp::where('user_id', $user->id)->priorityOrdered()->pluck('title')->all();

            expect($titles)->toBe(
                ['Urgent item', 'High item', 'Normal item', 'Low item'],
                'priorityOrdered must sort follow-ups urgent before high before normal before low'
            );
        });
    });

    describe('title and description split', function (): void {
        it('persists the title as the short label and accepts an optional description body', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'Chase the signed contract',
                'description' => 'A longer note explaining the context of the contract.',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $fresh = $followUp->fresh();

            expect($fresh->title)->toBe('Chase the signed contract', 'title must persist as the short label')
                ->and($fresh->description)->toBe('A longer note explaining the context of the contract.', 'description body must persist');
        });

        it('allows a null description body', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'No body needed',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->description)->toBeNull('description must be optional and default to null');
        });

        it('includes title in the fillable attributes', function (): void {
            expect((new FollowUp())->getFillable())->toContain('title');
        });
    });

    describe('priority and privacy', function (): void {
        it('casts priority to the Priority enum', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'Priority item',
                'priority' => Priority::High,
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->priority)->toBe(Priority::High, 'priority must be cast to the Priority enum');
        });

        it('defaults priority to normal when none is supplied', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'Default priority item',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->priority)->toBe(Priority::Normal, 'priority must default to normal');
        });

        it('casts is_private to a boolean', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'Private item',
                'is_private' => 1,
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->is_private)->toBeBool()->toBeTrue('is_private must be cast to a boolean');
        });

        it('defaults is_private to false', function (): void {
            $user = User::factory()->create();

            $followUp = FollowUp::create([
                'title' => 'Public item',
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            expect($followUp->fresh()->is_private)->toBeFalse('is_private must default to false');
        });
    });

    describe('filterable and searchable configuration', function (): void {
        it('exposes priority and is_private as filterable fields', function (): void {
            $property = new ReflectionProperty(FollowUp::class, 'filterableFields');
            $fields = $property->getValue(new FollowUp());

            expect($fields)->toHaveKey('priority')
                ->and($fields['priority'])->toBe('exact', 'priority must be an exact-match filter')
                ->and($fields)->toHaveKey('is_private')
                ->and($fields['is_private'])->toBe('boolean', 'is_private must be a boolean filter');
        });

        it('searches across title, description, and waiting_on', function (): void {
            $property = new ReflectionProperty(FollowUp::class, 'searchableFields');

            expect($property->getValue(new FollowUp()))
                ->toBe(['title', 'description', 'waiting_on'], 'search must cover title, description, and waiting_on');
        });
    });
});
