<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

describe('Filterable', function (): void {
    describe('exact match filtering', function (): void {
        it('returns only records matching an exact value', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            Task::create(['title' => 'Urgent task', 'priority' => Priority::Urgent, 'team_id' => $team->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Normal task', 'priority' => Priority::Normal, 'team_id' => $team->id, 'user_id' => $user->id]);

            $results = Task::applyFilters(['priority' => 'urgent'])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Urgent task');
        });

        it('returns all matching records when multiple share the same value', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            Task::create(['title' => 'Task 1', 'priority' => Priority::High, 'team_id' => $team->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Task 2', 'priority' => Priority::High, 'team_id' => $team->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Task 3', 'priority' => Priority::Low, 'team_id' => $team->id, 'user_id' => $user->id]);

            $results = Task::applyFilters(['priority' => 'high'])->get();

            expect($results)->toHaveCount(2);
        });
    });

    describe('like / partial match filtering', function (): void {
        it('returns records containing the partial value', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Frontend Team', 'user_id' => $user->id]);
            Team::create(['name' => 'Backend Team', 'user_id' => $user->id]);
            Team::create(['name' => 'Marketing', 'user_id' => $user->id]);

            $results = Team::applyFilters(['name' => 'Team'])->get();

            expect($results)->toHaveCount(2);
        });

        it('is case-insensitive for like filter', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'DevOps Team', 'user_id' => $user->id]);
            Team::create(['name' => 'HR', 'user_id' => $user->id]);

            $results = Team::applyFilters(['name' => 'devops'])->get();

            expect($results)->toHaveCount(1);
        });
    });

    describe('date range filtering', function (): void {
        it('filters records with deadline on or after from date', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Old', 'deadline' => '2024-01-01', 'user_id' => $user->id]);
            Task::create(['title' => 'New', 'deadline' => '2025-06-01', 'user_id' => $user->id]);

            $results = Task::applyFilters(['deadline' => ['from' => '2025-01-01']])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('New');
        });

        it('filters records with deadline on or before to date', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Early', 'deadline' => '2024-01-01', 'user_id' => $user->id]);
            Task::create(['title' => 'Late', 'deadline' => '2026-01-01', 'user_id' => $user->id]);

            $results = Task::applyFilters(['deadline' => ['to' => '2024-12-31']])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Early');
        });

        it('applies both from and to for a date range', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Before', 'deadline' => '2023-01-01', 'user_id' => $user->id]);
            Task::create(['title' => 'Within', 'deadline' => '2024-06-15', 'user_id' => $user->id]);
            Task::create(['title' => 'After', 'deadline' => '2025-12-31', 'user_id' => $user->id]);

            $results = Task::applyFilters(['deadline' => ['from' => '2024-01-01', 'to' => '2024-12-31']])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Within');
        });
    });

    describe('boolean filtering', function (): void {
        it('filters records where boolean field is true', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Private task', 'is_private' => true, 'user_id' => $user->id]);
            Task::create(['title' => 'Public task', 'is_private' => false, 'user_id' => $user->id]);

            $results = Task::applyFilters(['is_private' => true])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Private task');
        });

        it('filters records where boolean field is false', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Private task', 'is_private' => true, 'user_id' => $user->id]);
            Task::create(['title' => 'Public task', 'is_private' => false, 'user_id' => $user->id]);

            $results = Task::applyFilters(['is_private' => false])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Public task');
        });
    });

    describe('multiple combined filters', function (): void {
        it('applies multiple filters with AND logic', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            Task::create(['title' => 'Match', 'priority' => Priority::High, 'status' => TaskStatus::Open, 'team_id' => $team->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Wrong status', 'priority' => Priority::High, 'status' => TaskStatus::Done, 'team_id' => $team->id, 'user_id' => $user->id]);
            Task::create(['title' => 'Wrong priority', 'priority' => Priority::Low, 'status' => TaskStatus::Open, 'team_id' => $team->id, 'user_id' => $user->id]);

            $results = Task::applyFilters(['priority' => 'high', 'status' => 'open'])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Match');
        });
    });

    describe('empty and null filter values', function (): void {
        it('ignores filters with null values', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Task A', 'user_id' => $user->id]);
            Task::create(['title' => 'Task B', 'user_id' => $user->id]);

            $results = Task::applyFilters(['priority' => null])->get();

            expect($results)->toHaveCount(2);
        });

        it('ignores filters with empty string values', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Task A', 'user_id' => $user->id]);
            Task::create(['title' => 'Task B', 'user_id' => $user->id]);

            $results = Task::applyFilters(['priority' => ''])->get();

            expect($results)->toHaveCount(2);
        });

        it('ignores filter keys not defined in filterableFields', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Task A', 'user_id' => $user->id]);

            $results = Task::applyFilters(['nonexistent_field' => 'some_value'])->get();

            expect($results)->toHaveCount(1);
        });

        it('returns all records when filters array is empty', function (): void {
            $user = User::factory()->create();
            Task::create(['title' => 'Task A', 'user_id' => $user->id]);
            Task::create(['title' => 'Task B', 'user_id' => $user->id]);

            $results = Task::applyFilters([])->get();

            expect($results)->toHaveCount(2);
        });
    });
});
