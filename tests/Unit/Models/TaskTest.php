<?php

declare(strict_types=1);

use App\Enums\Priority;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            $task = Task::create([
                'title' => 'My Task',
                'description' => 'Task description',
                'priority' => Priority::High,
                'status' => TaskStatus::InProgress,
                'is_private' => true,
            ]);

            expect($task->title)->toBe('My Task')
                ->and($task->description)->toBe('Task description')
                ->and($task->is_private)->toBeTrue();
        });
    });

    describe('enum casts', function (): void {
        it('casts priority to Priority enum', function (): void {
            $task = Task::create(['title' => 'Urgent task', 'priority' => Priority::Urgent]);

            expect($task->fresh()->priority)->toBe(Priority::Urgent);
        });

        it('casts status to TaskStatus enum', function (): void {
            $task = Task::create(['title' => 'In-progress task', 'status' => TaskStatus::InProgress]);

            expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
        });

        it('casts deadline to a Carbon date instance', function (): void {
            $task = Task::create(['title' => 'Task with deadline', 'deadline' => '2025-12-31']);

            expect($task->fresh()->deadline)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts is_private to boolean', function (): void {
            $task = Task::create(['title' => 'Private task', 'is_private' => true]);

            expect($task->fresh()->is_private)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Team', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $task = Task::create(['title' => 'Task', 'team_id' => $team->id]);

            expect($task->team())->toBeInstanceOf(BelongsTo::class)
                ->and($task->team->id)->toBe($team->id);
        });

        it('belongs to a TeamMember', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $task = Task::create(['title' => 'Task', 'team_member_id' => $member->id]);

            expect($task->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($task->teamMember->id)->toBe($member->id);
        });

        it('belongs to a TaskGroup', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint 1']);
            $task = Task::create(['title' => 'Task', 'task_group_id' => $group->id]);

            expect($task->taskGroup())->toBeInstanceOf(BelongsTo::class)
                ->and($task->taskGroup->id)->toBe($group->id);
        });

        it('belongs to a TaskCategory', function (): void {
            $category = TaskCategory::create(['name' => 'Bug']);
            $task = Task::create(['title' => 'Task', 'task_category_id' => $category->id]);

            expect($task->taskCategory())->toBeInstanceOf(BelongsTo::class)
                ->and($task->taskCategory->id)->toBe($category->id);
        });

        it('has a hasMany relationship to FollowUp', function (): void {
            $task = Task::create(['title' => 'Task']);

            expect($task->followUps())->toBeInstanceOf(HasMany::class);
        });

        it('returns related follow-ups', function (): void {
            $task = Task::create(['title' => 'Task']);
            FollowUp::create(['task_id' => $task->id, 'description' => 'Follow up', 'status' => 'open']);

            expect($task->followUps)->toHaveCount(1);
        });

        it('returns null for optional relationships when not set', function (): void {
            $task = Task::create(['title' => 'Standalone task']);

            expect($task->team)->toBeNull()
                ->and($task->teamMember)->toBeNull()
                ->and($task->taskGroup)->toBeNull()
                ->and($task->taskCategory)->toBeNull();
        });
    });
});
