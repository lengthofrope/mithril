<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('TaskCategory model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(TaskCategory::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(TaskCategory::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of name and sort_order', function (): void {
            $user = User::factory()->create();
            $category = TaskCategory::create([
                'name' => 'Bug',
                'sort_order' => 3,
                'user_id' => $user->id,
            ]);

            expect($category->name)->toBe('Bug')
                ->and($category->sort_order)->toBe(3);
        });
    });

    describe('relationships', function (): void {
        it('has a hasMany relationship to Task', function (): void {
            $user = User::factory()->create();
            $category = TaskCategory::create(['name' => 'Bug', 'user_id' => $user->id]);

            expect($category->tasks())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tasks', function (): void {
            $user = User::factory()->create();
            $category = TaskCategory::create(['name' => 'Bug', 'user_id' => $user->id]);
            Task::create(['title' => 'Bug fix 1', 'task_category_id' => $category->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Bug fix 2', 'task_category_id' => $category->id, 'user_id' => $user->id]);

            expect($category->tasks)->toHaveCount(2);
        });

        it('does not include tasks from other categories', function (): void {
            $user = User::factory()->create();
            $bugs = TaskCategory::create(['name' => 'Bug', 'user_id' => $user->id]);
            $features = TaskCategory::create(['name' => 'Feature', 'user_id' => $user->id]);
            Task::create(['title' => 'A bug', 'task_category_id' => $bugs->id, 'user_id' => $user->id]);
            Task::create(['title' => 'A feature', 'task_category_id' => $features->id, 'user_id' => $user->id]);

            expect($bugs->tasks)->toHaveCount(1)
                ->and($bugs->tasks->first()->title)->toBe('A bug');
        });
    });
});
