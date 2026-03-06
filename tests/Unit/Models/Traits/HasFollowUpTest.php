<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
/**
 * Tests for the HasFollowUp trait.
 *
 * The trait provides:
 *   - A followUps() hasMany relationship on the using model
 *   - Timeline scopes (overdue, dueToday, dueThisWeek, upcoming) intended for
 *     use on models that have follow_up_date and status columns (i.e. FollowUp
 *     itself, which owns its own equivalent scope definitions)
 *
 * The relationship is tested on Task (which uses HasFollowUp).
 * The scopes are exercised on FollowUp's own scope methods, which mirror the
 * trait implementation exactly.
 */
describe('HasFollowUp', function (): void {
    describe('followUps() hasMany relationship', function (): void {
        it('provides a followUps relationship on Task', function (): void {
            $task = Task::create(['title' => 'Task with follow-ups']);
            FollowUp::create(['task_id' => $task->id, 'description' => 'FU 1', 'status' => FollowUpStatus::Open]);
            FollowUp::create(['task_id' => $task->id, 'description' => 'FU 2', 'status' => FollowUpStatus::Open]);

            expect($task->followUps)->toHaveCount(2);
        });

        it('provides a followUps relationship on TeamMember', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            FollowUp::create(['team_member_id' => $member->id, 'description' => 'FU 1', 'status' => FollowUpStatus::Open]);

            expect($member->followUps)->toHaveCount(1);
        });

        it('does not mix follow-ups between tasks', function (): void {
            $taskA = Task::create(['title' => 'Task A']);
            $taskB = Task::create(['title' => 'Task B']);
            FollowUp::create(['task_id' => $taskA->id, 'description' => 'For A', 'status' => FollowUpStatus::Open]);
            FollowUp::create(['task_id' => $taskB->id, 'description' => 'For B', 'status' => FollowUpStatus::Open]);

            expect($taskA->followUps)->toHaveCount(1)
                ->and($taskA->followUps->first()->description)->toBe('For A');
        });

        it('returns an empty collection when no follow-ups exist', function (): void {
            $task = Task::create(['title' => 'No follow-ups task']);

            expect($task->followUps)->toHaveCount(0);
        });
    });

    describe('overdue scope (via FollowUp model)', function (): void {
        it('returns follow-ups with a past date and non-done status', function (): void {
            FollowUp::create([
                'description' => 'Overdue',
                'follow_up_date' => now()->subDay(),
                'status' => FollowUpStatus::Open,
            ]);
            FollowUp::create([
                'description' => 'Overdue but done',
                'follow_up_date' => now()->subDay(),
                'status' => FollowUpStatus::Done,
            ]);
            FollowUp::create([
                'description' => 'Future',
                'follow_up_date' => now()->addDay(),
                'status' => FollowUpStatus::Open,
            ]);

            expect(FollowUp::overdue()->count())->toBe(1)
                ->and(FollowUp::overdue()->first()->description)->toBe('Overdue');
        });
    });

    describe('dueToday scope (via FollowUp model)', function (): void {
        it('returns open follow-ups due today', function (): void {
            FollowUp::create([
                'description' => 'Today open',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Open,
            ]);
            FollowUp::create([
                'description' => 'Today done',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Done,
            ]);

            expect(FollowUp::dueToday()->count())->toBe(1)
                ->and(FollowUp::dueToday()->first()->description)->toBe('Today open');
        });
    });

    describe('dueThisWeek scope (via FollowUp model)', function (): void {
        it('returns open follow-ups after today and within this week', function (): void {
            $endOfWeek = now()->endOfWeek();
            $todayIsEndOfWeek = now()->isSameDay($endOfWeek);

            // Use a mid-week date that is always after today and within the week,
            // unless today is already the last day of the week.
            $withinWeekDate = $todayIsEndOfWeek
                ? $endOfWeek
                : now()->addDay();

            FollowUp::create([
                'description' => 'This week',
                'follow_up_date' => $withinWeekDate,
                'status' => FollowUpStatus::Open,
            ]);
            FollowUp::create([
                'description' => 'Today',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Open,
            ]);
            FollowUp::create([
                'description' => 'Next week',
                'follow_up_date' => now()->addWeeks(2),
                'status' => FollowUpStatus::Open,
            ]);

            if ($todayIsEndOfWeek) {
                // When today is end of week, no date qualifies as "this week after today"
                expect(FollowUp::dueThisWeek()->count())->toBe(0);
            } else {
                expect(FollowUp::dueThisWeek()->count())->toBe(1)
                    ->and(FollowUp::dueThisWeek()->first()->description)->toBe('This week');
            }
        });
    });

    describe('upcoming scope (via FollowUp model)', function (): void {
        it('returns open follow-ups due after the current week', function (): void {
            $afterWeek = now()->endOfWeek()->addDay();

            FollowUp::create([
                'description' => 'Upcoming',
                'follow_up_date' => $afterWeek,
                'status' => FollowUpStatus::Open,
            ]);
            FollowUp::create([
                'description' => 'This week',
                'follow_up_date' => now()->endOfWeek(),
                'status' => FollowUpStatus::Open,
            ]);

            expect(FollowUp::upcoming()->count())->toBe(1)
                ->and(FollowUp::upcoming()->first()->description)->toBe('Upcoming');
        });
    });
});
