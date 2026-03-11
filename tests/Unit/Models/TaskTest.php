<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\RecurrenceInterval;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

describe('Task model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(Task::class)))->toBeTrue();
        });

        it('uses the Filterable trait', function (): void {
            expect(in_array(Filterable::class, class_uses_recursive(Task::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(Task::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $task = Task::create([
                'title' => 'My Task',
                'description' => 'Task description',
                'priority' => Priority::High,
                'status' => TaskStatus::InProgress,
                'is_private' => true,
                'user_id' => $user->id,
            ]);

            expect($task->title)->toBe('My Task')
                ->and($task->description)->toBe('Task description')
                ->and($task->is_private)->toBeTrue();
        });
    });

    describe('enum casts', function (): void {
        it('casts priority to Priority enum', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Urgent task', 'priority' => Priority::Urgent, 'user_id' => $user->id]);

            expect($task->fresh()->priority)->toBe(Priority::Urgent);
        });

        it('casts status to TaskStatus enum', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'In-progress task', 'status' => TaskStatus::InProgress, 'user_id' => $user->id]);

            expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
        });

        it('casts deadline to a Carbon date instance', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Task with deadline', 'deadline' => '2025-12-31', 'user_id' => $user->id]);

            expect($task->fresh()->deadline)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts is_private to boolean', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Private task', 'is_private' => true, 'user_id' => $user->id]);

            expect($task->fresh()->is_private)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Team', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $task = Task::create(['title' => 'Task', 'team_id' => $team->id, 'user_id' => $user->id]);

            expect($task->team())->toBeInstanceOf(BelongsTo::class)
                ->and($task->team->id)->toBe($team->id);
        });

        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $task = Task::create(['title' => 'Task', 'team_member_id' => $member->id, 'user_id' => $user->id]);

            expect($task->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($task->teamMember->id)->toBe($member->id);
        });

        it('belongs to a TaskGroup', function (): void {
            $user = User::factory()->create();
            $group = TaskGroup::create(['name' => 'Sprint 1', 'user_id' => $user->id]);
            $task = Task::create(['title' => 'Task', 'task_group_id' => $group->id, 'user_id' => $user->id]);

            expect($task->taskGroup())->toBeInstanceOf(BelongsTo::class)
                ->and($task->taskGroup->id)->toBe($group->id);
        });

        it('belongs to a TaskCategory', function (): void {
            $user = User::factory()->create();
            $category = TaskCategory::create(['name' => 'Bug', 'user_id' => $user->id]);
            $task = Task::create(['title' => 'Task', 'task_category_id' => $category->id, 'user_id' => $user->id]);

            expect($task->taskCategory())->toBeInstanceOf(BelongsTo::class)
                ->and($task->taskCategory->id)->toBe($category->id);
        });

        it('has a hasMany relationship to FollowUp', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Task', 'user_id' => $user->id]);

            expect($task->followUps())->toBeInstanceOf(HasMany::class);
        });

        it('returns related follow-ups', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Task', 'user_id' => $user->id]);
            FollowUp::create(['task_id' => $task->id, 'description' => 'Follow up', 'status' => 'open', 'user_id' => $user->id]);

            expect($task->followUps)->toHaveCount(1);
        });

        it('returns null for optional relationships when not set', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Standalone task', 'user_id' => $user->id]);

            expect($task->team)->toBeNull()
                ->and($task->teamMember)->toBeNull()
                ->and($task->taskGroup)->toBeNull()
                ->and($task->taskCategory)->toBeNull();
        });
    });

    describe('recurrence fillable attributes', function (): void {
        it('allows mass assignment of recurrence fields', function (): void {
            $user = User::factory()->create();
            $task = Task::create([
                'title' => 'Recurring task',
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
                'recurrence_custom_days' => null,
                'recurrence_series_id' => 'abc-123',
                'recurrence_parent_id' => null,
            ]);

            expect($task->is_recurring)->toBeTrue()
                ->and($task->recurrence_interval)->toBe(RecurrenceInterval::Weekly)
                ->and($task->recurrence_series_id)->toBe('abc-123');
        });
    });

    describe('recurrence enum casts', function (): void {
        it('casts recurrence_interval to RecurrenceInterval enum', function (): void {
            $user = User::factory()->create();
            $task = Task::create([
                'title' => 'Monthly task',
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Monthly,
            ]);

            expect($task->fresh()->recurrence_interval)->toBe(RecurrenceInterval::Monthly);
        });

        it('casts recurrence_interval to null when not set', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Non-recurring task', 'user_id' => $user->id]);

            expect($task->fresh()->recurrence_interval)->toBeNull();
        });
    });

    describe('recurrence boolean cast', function (): void {
        it('casts is_recurring to boolean true', function (): void {
            $user = User::factory()->create();
            $task = Task::create([
                'title' => 'Recurring',
                'user_id' => $user->id,
                'is_recurring' => true,
            ]);

            expect($task->fresh()->is_recurring)->toBeTrue();
        });

        it('casts is_recurring to boolean false by default', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Normal', 'user_id' => $user->id]);

            expect($task->fresh()->is_recurring)->toBeFalse();
        });
    });

    describe('recurrence relationships', function (): void {
        it('has a recurrenceParent BelongsTo relationship', function (): void {
            $user = User::factory()->create();
            $parent = Task::factory()->create(['user_id' => $user->id, 'is_recurring' => true]);
            $child = Task::factory()->create([
                'user_id' => $user->id,
                'recurrence_parent_id' => $parent->id,
            ]);

            expect($child->recurrenceParent())->toBeInstanceOf(BelongsTo::class)
                ->and($child->recurrenceParent->id)->toBe($parent->id);
        });

        it('has a recurrenceChild HasOne relationship', function (): void {
            $user = User::factory()->create();
            $parent = Task::factory()->create(['user_id' => $user->id, 'is_recurring' => true]);
            $child = Task::factory()->create([
                'user_id' => $user->id,
                'recurrence_parent_id' => $parent->id,
            ]);

            expect($parent->recurrenceChild())->toBeInstanceOf(HasOne::class)
                ->and($parent->recurrenceChild->id)->toBe($child->id);
        });

        it('has a seriesTasks HasMany relationship', function (): void {
            $user = User::factory()->create();
            $seriesId = (string) \Illuminate\Support\Str::uuid();

            $task1 = Task::factory()->create([
                'user_id' => $user->id,
                'recurrence_series_id' => $seriesId,
            ]);
            $task2 = Task::factory()->create([
                'user_id' => $user->id,
                'recurrence_series_id' => $seriesId,
            ]);
            Task::factory()->create(['user_id' => $user->id]);

            expect($task1->seriesTasks())->toBeInstanceOf(HasMany::class)
                ->and($task1->seriesTasks)->toHaveCount(2);
        });

        it('returns null recurrenceParent when not set', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            expect($task->recurrenceParent)->toBeNull();
        });

        it('returns null recurrenceChild when no child exists', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            expect($task->recurrenceChild)->toBeNull();
        });
    });

    describe('is_recurring filter', function (): void {
        it('filters tasks by is_recurring boolean', function (): void {
            $user = User::factory()->create();

            Task::factory()->create(['user_id' => $user->id, 'is_recurring' => true]);
            Task::factory()->create(['user_id' => $user->id, 'is_recurring' => false]);

            $recurring = Task::applyFilters(['is_recurring' => true])->get();
            $nonRecurring = Task::applyFilters(['is_recurring' => false])->get();

            expect($recurring)->toHaveCount(1)
                ->and($nonRecurring)->toHaveCount(1);
        });
    });

    describe('recurrence_series_id auto-generation', function (): void {
        it('auto-generates a UUID for recurrence_series_id when is_recurring is set to true', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => false,
                'recurrence_series_id' => null,
            ]);

            $task->update(['is_recurring' => true]);

            expect($task->fresh()->recurrence_series_id)->not->toBeNull()
                ->and($task->fresh()->recurrence_series_id)->toMatch('/^[0-9a-f\-]{36}$/');
        });

        it('keeps existing recurrence_series_id when is_recurring is already true', function (): void {
            $user = User::factory()->create();
            $existingSeriesId = (string) \Illuminate\Support\Str::uuid();

            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_series_id' => $existingSeriesId,
            ]);

            $task->update(['title' => 'Updated title']);

            expect($task->fresh()->recurrence_series_id)->toBe($existingSeriesId);
        });

        it('does not generate recurrence_series_id when is_recurring is false', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => false,
                'recurrence_series_id' => null,
            ]);

            $task->update(['title' => 'Some update']);

            expect($task->fresh()->recurrence_series_id)->toBeNull();
        });
    });
});
