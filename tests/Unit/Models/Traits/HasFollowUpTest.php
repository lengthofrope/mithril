<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

/**
 * Tests for the HasFollowUp trait.
 *
 * The trait provides:
 *   - A followUps() hasMany relationship on the using model
 *   - whereHas-based scopes that filter the parent model by the state of its
 *     related follow-ups (withOverdueFollowUps, withFollowUpsDueToday, etc.)
 */
describe('HasFollowUp', function (): void {
    describe('followUps() hasMany relationship', function (): void {
        it('provides a followUps relationship on Task', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'Task with follow-ups', 'user_id' => $user->id]);
            FollowUp::create(['task_id' => $task->id, 'description' => 'FU 1', 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['task_id' => $task->id, 'description' => 'FU 2', 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect($task->followUps)->toHaveCount(2);
        });

        it('provides a followUps relationship on TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            FollowUp::create(['team_member_id' => $member->id, 'description' => 'FU 1', 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect($member->followUps)->toHaveCount(1);
        });

        it('does not mix follow-ups between tasks', function (): void {
            $user = User::factory()->create();
            $taskA = Task::create(['title' => 'Task A', 'user_id' => $user->id]);
            $taskB = Task::create(['title' => 'Task B', 'user_id' => $user->id]);
            FollowUp::create(['task_id' => $taskA->id, 'description' => 'For A', 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);
            FollowUp::create(['task_id' => $taskB->id, 'description' => 'For B', 'status' => FollowUpStatus::Open, 'user_id' => $user->id]);

            expect($taskA->followUps)->toHaveCount(1)
                ->and($taskA->followUps->first()->description)->toBe('For A');
        });

        it('returns an empty collection when no follow-ups exist', function (): void {
            $user = User::factory()->create();
            $task = Task::create(['title' => 'No follow-ups task', 'user_id' => $user->id]);

            expect($task->followUps)->toHaveCount(0);
        });
    });

    describe('withOverdueFollowUps scope', function (): void {
        it('returns tasks that have at least one overdue non-done follow-up', function (): void {
            $user = User::factory()->create();
            $taskWithOverdue = Task::create(['title' => 'Has overdue', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskWithOverdue->id,
                'description' => 'Overdue',
                'follow_up_date' => now()->subDay(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $taskWithDone = Task::create(['title' => 'Has done overdue', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskWithDone->id,
                'description' => 'Overdue but done',
                'follow_up_date' => now()->subDay(),
                'status' => FollowUpStatus::Done,
                'user_id' => $user->id,
            ]);

            $taskWithFuture = Task::create(['title' => 'Has future', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskWithFuture->id,
                'description' => 'Future',
                'follow_up_date' => now()->addDay(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $results = Task::withOverdueFollowUps()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Has overdue');
        });
    });

    describe('withFollowUpsDueToday scope', function (): void {
        it('returns tasks that have at least one follow-up due today and not done', function (): void {
            $user = User::factory()->create();
            $taskToday = Task::create(['title' => 'Due today', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskToday->id,
                'description' => 'Today open',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $taskTodayDone = Task::create(['title' => 'Due today but done', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskTodayDone->id,
                'description' => 'Today done',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Done,
                'user_id' => $user->id,
            ]);

            $results = Task::withFollowUpsDueToday()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Due today');
        });
    });

    describe('withFollowUpsDueThisWeek scope', function (): void {
        it('returns tasks with follow-ups after today and within this week', function (): void {
            $user = User::factory()->create();
            $endOfWeek = now()->endOfWeek();
            $todayIsEndOfWeek = now()->isSameDay($endOfWeek);

            $taskThisWeek = Task::create(['title' => 'This week', 'user_id' => $user->id]);
            $withinWeekDate = $todayIsEndOfWeek ? $endOfWeek : now()->addDay();
            FollowUp::create([
                'task_id' => $taskThisWeek->id,
                'description' => 'This week',
                'follow_up_date' => $withinWeekDate,
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $taskToday = Task::create(['title' => 'Today task', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskToday->id,
                'description' => 'Today',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $results = Task::withFollowUpsDueThisWeek()->get();

            if ($todayIsEndOfWeek) {
                expect($results)->toHaveCount(0);
            } else {
                expect($results)->toHaveCount(1)
                    ->and($results->first()->title)->toBe('This week');
            }
        });
    });

    describe('withUpcomingFollowUps scope', function (): void {
        it('returns tasks with follow-ups due after the current week', function (): void {
            $user = User::factory()->create();
            $taskUpcoming = Task::create(['title' => 'Upcoming task', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskUpcoming->id,
                'description' => 'Upcoming',
                'follow_up_date' => now()->endOfWeek()->addDay(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $taskThisWeek = Task::create(['title' => 'This week task', 'user_id' => $user->id]);
            FollowUp::create([
                'task_id' => $taskThisWeek->id,
                'description' => 'This week',
                'follow_up_date' => now()->endOfWeek(),
                'status' => FollowUpStatus::Open,
                'user_id' => $user->id,
            ]);

            $results = Task::withUpcomingFollowUps()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Upcoming task');
        });
    });
});
