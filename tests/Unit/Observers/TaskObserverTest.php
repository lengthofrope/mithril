<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Events\TaskStatusChanged;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('TaskObserver', function (): void {
    describe('updated hook', function (): void {
        it('dispatches TaskStatusChanged when status changes', function (): void {
            Event::fake([TaskStatusChanged::class]);

            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::Open,
            ]);

            $task->update(['status' => TaskStatus::Done]);

            Event::assertDispatched(TaskStatusChanged::class, function (TaskStatusChanged $event) use ($task): bool {
                return $event->task->id === $task->id
                    && $event->oldStatus === TaskStatus::Open
                    && $event->newStatus === TaskStatus::Done;
            });
        });

        it('dispatches TaskStatusChanged with correct old and new status values', function (): void {
            Event::fake([TaskStatusChanged::class]);

            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::InProgress,
            ]);

            $task->update(['status' => TaskStatus::Waiting]);

            Event::assertDispatched(TaskStatusChanged::class, function (TaskStatusChanged $event): bool {
                return $event->oldStatus === TaskStatus::InProgress
                    && $event->newStatus === TaskStatus::Waiting;
            });
        });

        it('does not dispatch TaskStatusChanged when status does not change', function (): void {
            Event::fake([TaskStatusChanged::class]);

            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::Open,
            ]);

            $task->update(['title' => 'Updated title']);

            Event::assertNotDispatched(TaskStatusChanged::class);
        });

        it('does not dispatch TaskStatusChanged when updating the same status value', function (): void {
            Event::fake([TaskStatusChanged::class]);

            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::Open,
            ]);

            $task->update(['status' => TaskStatus::Open]);

            Event::assertNotDispatched(TaskStatusChanged::class);
        });

        it('dispatches exactly once per status-change update', function (): void {
            Event::fake([TaskStatusChanged::class]);

            $user = User::factory()->create();
            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::Open,
            ]);

            $task->update(['status' => TaskStatus::Done]);

            Event::assertDispatchedTimes(TaskStatusChanged::class, 1);
        });
    });
});
