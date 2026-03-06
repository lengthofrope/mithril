<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Relations\HasMany;
describe('TaskGroup model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(TaskGroup::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(TaskGroup::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of name, description, color, and sort_order', function (): void {
            $group = TaskGroup::create([
                'name' => 'Sprint 1',
                'description' => 'First sprint',
                'color' => '#00ff00',
                'sort_order' => 2,
            ]);

            expect($group->name)->toBe('Sprint 1')
                ->and($group->description)->toBe('First sprint')
                ->and($group->color)->toBe('#00ff00')
                ->and($group->sort_order)->toBe(2);
        });
    });

    describe('relationships', function (): void {
        it('has a hasMany relationship to Task', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint 1']);

            expect($group->tasks())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tasks', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint 1']);
            Task::create(['title' => 'Task A', 'task_group_id' => $group->id]);
            Task::create(['title' => 'Task B', 'task_group_id' => $group->id]);

            expect($group->tasks)->toHaveCount(2);
        });

        it('does not include tasks from other groups', function (): void {
            $groupA = TaskGroup::create(['name' => 'Sprint 1']);
            $groupB = TaskGroup::create(['name' => 'Sprint 2']);
            Task::create(['title' => 'Task in A', 'task_group_id' => $groupA->id]);
            Task::create(['title' => 'Task in B', 'task_group_id' => $groupB->id]);

            expect($groupA->tasks)->toHaveCount(1)
                ->and($groupA->tasks->first()->title)->toBe('Task in A');
        });
    });
});
