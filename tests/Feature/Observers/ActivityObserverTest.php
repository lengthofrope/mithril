<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ActivityObserver', function (): void {
    describe('status changes', function (): void {
        it('logs a system event when task status changes', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
            $task->update(['status' => TaskStatus::Done]);

            $activity = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->body)->toContain('Status changed')
                ->and($activity->metadata['action'])->toBe('status_changed')
                ->and($activity->metadata['changes']['status']['old'])->toBe(TaskStatus::Open->value)
                ->and($activity->metadata['changes']['status']['new'])->toBe(TaskStatus::Done->value);
        });

        it('logs a system event when follow-up status changes', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $followUp = FollowUp::factory()->create(['user_id' => $user->id, 'status' => FollowUpStatus::Open]);
            $followUp->update(['status' => FollowUpStatus::Done]);

            $activity = Activity::where('activityable_type', FollowUp::class)
                ->where('activityable_id', $followUp->id)
                ->where('type', 'system')
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->metadata['changes']['status'])->not->toBeNull();
        });
    });

    describe('priority changes', function (): void {
        it('logs a system event when task priority changes', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal]);
            $task->update(['priority' => Priority::Urgent]);

            $activity = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->body)->toContain('Priority changed')
                ->and($activity->metadata['action'])->toBe('priority_changed')
                ->and($activity->metadata['changes']['priority']['old'])->toBe(Priority::Normal->value)
                ->and($activity->metadata['changes']['priority']['new'])->toBe(Priority::Urgent->value);
        });
    });

    describe('completion changes', function (): void {
        it('logs a system event when task is completed', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
            $task->update(['status' => TaskStatus::Done]);

            $activities = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->get();

            expect($activities)->not->toBeEmpty();
        });

        it('logs a system event when bila is_done changes', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $bila = Bila::factory()->create(['user_id' => $user->id, 'is_done' => false]);
            $bila->update(['is_done' => true]);

            $activity = Activity::where('activityable_type', Bila::class)
                ->where('activityable_id', $bila->id)
                ->where('type', 'system')
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->metadata['changes'])->toHaveKey('is_done');
        });
    });

    describe('snoozed_until changes', function (): void {
        it('logs a system event when follow-up is snoozed', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $followUp = FollowUp::factory()->create(['user_id' => $user->id, 'snoozed_until' => null]);
            $followUp->update(['snoozed_until' => now()->addDays(3)->toDateString()]);

            $activity = Activity::where('activityable_type', FollowUp::class)
                ->where('activityable_id', $followUp->id)
                ->where('type', 'system')
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->metadata['changes'])->toHaveKey('snoozed_until');
        });
    });

    describe('no-op scenarios', function (): void {
        it('does not create activity when no tracked fields changed', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Original']);
            $task->update(['title' => 'Updated Title']);

            $activities = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->get();

            expect($activities)->toBeEmpty();
        });

        it('does not create activity when value does not actually change', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal]);
            $task->update(['priority' => Priority::Normal]);

            $activities = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->get();

            expect($activities)->toBeEmpty();
        });
    });

    describe('system activity metadata', function (): void {
        it('includes action and changes keys in metadata', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
            $task->update(['status' => TaskStatus::InProgress]);

            $activity = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->first();

            expect($activity->metadata)->toHaveKeys(['action', 'changes']);
        });

        it('uses the authenticated user for the system activity', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
            $task->update(['status' => TaskStatus::Done]);

            $activity = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->first();

            expect($activity->user_id)->toBe($user->id);
        });
    });

    describe('multiple field changes in one update', function (): void {
        it('logs changes for each tracked field that changed', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $task = Task::factory()->create([
                'user_id' => $user->id,
                'status' => TaskStatus::Open,
                'priority' => Priority::Normal,
            ]);

            $task->update([
                'status' => TaskStatus::Done,
                'priority' => Priority::Urgent,
            ]);

            $activities = Activity::where('activityable_type', Task::class)
                ->where('activityable_id', $task->id)
                ->where('type', 'system')
                ->get();

            expect($activities->count())->toBeGreaterThanOrEqual(1);

            $allChanges = $activities->flatMap(fn ($a) => array_keys($a->metadata['changes'] ?? []));
            expect($allChanges)->toContain('status')
                ->and($allChanges)->toContain('priority');
        });
    });
});
