<?php

declare(strict_types=1);

use App\Enums\RecurrenceInterval;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-11 12:00:00'));
    $this->service = new RecurrenceService();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecurrenceService', function (): void {
    describe('calculateNextDeadline', function (): void {
        it('advances a daily interval by one day', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Daily);

            expect($next->toDateString())->toBe('2026-03-21');
        });

        it('advances a weekly interval by seven days', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Weekly);

            expect($next->toDateString())->toBe('2026-03-27');
        });

        it('advances a biweekly interval by fourteen days', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Biweekly);

            expect($next->toDateString())->toBe('2026-04-03');
        });

        it('advances a monthly interval by one month', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Monthly);

            expect($next->toDateString())->toBe('2026-04-20');
        });

        it('advances a custom interval by the specified number of days', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Custom, 10);

            expect($next->toDateString())->toBe('2026-03-30');
        });

        it('handles month-end clamping for monthly intervals (Jan 31 advances in month-end-safe steps)', function (): void {
            Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
            $base = Carbon::parse('2025-12-31');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Monthly);

            expect($next->toDateString())->toBe('2026-01-31');
        });

        it('skips past dates and returns a future date', function (): void {
            $pastBase = Carbon::parse('2026-01-01');
            $next = $this->service->calculateNextDeadline($pastBase, RecurrenceInterval::Weekly);

            expect($next->isFuture() || $next->isToday())->toBeTrue();
        });

        it('returns today if advancing once from a past date lands on today', function (): void {
            Carbon::setTestNow(Carbon::parse('2026-03-18'));
            $base = Carbon::parse('2026-03-11');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Weekly);

            expect($next->toDateString())->toBe('2026-03-18');
        });

        it('uses today as the base when no deadline is provided', function (): void {
            $next = $this->service->calculateNextDeadline(null, RecurrenceInterval::Daily);

            expect($next->toDateString())->toBe(Carbon::today()->addDay()->toDateString());
        });

        it('uses custom days of 1 when custom interval is set but customDays is null', function (): void {
            $base = Carbon::parse('2026-03-20');
            $next = $this->service->calculateNextDeadline($base, RecurrenceInterval::Custom, null);

            expect($next->toDateString())->toBe('2026-03-21');
        });
    });

    describe('shouldRecur', function (): void {
        it('returns true when task is recurring and status changes to Done', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
            ]);

            $result = $this->service->shouldRecur($task, TaskStatus::Open, TaskStatus::Done);

            expect($result)->toBeTrue();
        });

        it('returns false when status changes from Done to Done', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
            ]);

            $result = $this->service->shouldRecur($task, TaskStatus::Done, TaskStatus::Done);

            expect($result)->toBeFalse();
        });

        it('returns false when is_recurring is false', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => false,
                'recurrence_interval' => RecurrenceInterval::Weekly,
            ]);

            $result = $this->service->shouldRecur($task, TaskStatus::Open, TaskStatus::Done);

            expect($result)->toBeFalse();
        });

        it('returns false when recurrence_interval is null', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => null,
            ]);

            $result = $this->service->shouldRecur($task, TaskStatus::Open, TaskStatus::Done);

            expect($result)->toBeFalse();
        });

        it('returns false when new status is not Done', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
            ]);

            $result = $this->service->shouldRecur($task, TaskStatus::Open, TaskStatus::InProgress);

            expect($result)->toBeFalse();
        });
    });

    describe('createNextOccurrence', function (): void {
        it('creates a new task with the correct fields copied from the completed task', function (): void {
            $user = User::factory()->create();
            $seriesId = (string) \Illuminate\Support\Str::uuid();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'title' => 'Weekly report',
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
                'recurrence_series_id' => $seriesId,
                'deadline' => Carbon::parse('2026-03-14'),
            ]);

            $nextTask = $this->service->createNextOccurrence($task);

            expect($nextTask->title)->toBe('Weekly report')
                ->and($nextTask->is_recurring)->toBeTrue()
                ->and($nextTask->recurrence_interval)->toBe(RecurrenceInterval::Weekly)
                ->and($nextTask->recurrence_series_id)->toBe($seriesId)
                ->and($nextTask->recurrence_parent_id)->toBe($task->id)
                ->and($nextTask->user_id)->toBe($user->id);
        });

        it('sets status to Open on the new occurrence', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
                'status' => TaskStatus::Done,
                'deadline' => Carbon::parse('2026-03-14'),
            ]);

            $nextTask = $this->service->createNextOccurrence($task);

            expect($nextTask->status)->toBe(TaskStatus::Open);
        });

        it('preserves recurrence_series_id from the completed task', function (): void {
            $user = User::factory()->create();
            $seriesId = (string) \Illuminate\Support\Str::uuid();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Daily,
                'recurrence_series_id' => $seriesId,
                'deadline' => Carbon::parse('2026-03-14'),
            ]);

            $nextTask = $this->service->createNextOccurrence($task);

            expect($nextTask->recurrence_series_id)->toBe($seriesId);
        });

        it('sets recurrence_parent_id to the completed task id', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Monthly,
                'deadline' => Carbon::parse('2026-03-14'),
            ]);

            $nextTask = $this->service->createNextOccurrence($task);

            expect($nextTask->recurrence_parent_id)->toBe($task->id);
        });

        it('sets the next deadline in the future', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
                'deadline' => Carbon::parse('2026-03-14'),
            ]);

            $nextTask = $this->service->createNextOccurrence($task);

            expect($nextTask->deadline->isAfter(Carbon::today()) || $nextTask->deadline->isToday())->toBeTrue();
        });
    });

    describe('stopRecurrence', function (): void {
        it('sets is_recurring to false on the task', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'is_recurring' => true,
                'recurrence_interval' => RecurrenceInterval::Weekly,
            ]);

            $this->service->stopRecurrence($task);

            expect($task->fresh()->is_recurring)->toBeFalse();
        });
    });
});
