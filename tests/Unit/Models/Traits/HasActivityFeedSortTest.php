<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('HasActivityFeed::getActivityFeed sort order', function (): void {
    it('returns activities in ascending (oldest-first) order when sort direction is asc', function (): void {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'First comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Second comment',
            'created_at'        => now()->subMinutes(5),
        ]);

        $activities = $task->getActivityFeed('asc');

        expect($activities)->toHaveCount(2)
            ->and($activities->first()->body)->toBe('First comment', 'oldest activity should appear first when sort is asc')
            ->and($activities->last()->body)->toBe('Second comment');
    });

    it('returns activities in descending (newest-first) order when sort direction is desc', function (): void {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'First comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Second comment',
            'created_at'        => now()->subMinutes(5),
        ]);

        $activities = $task->getActivityFeed('desc');

        expect($activities)->toHaveCount(2)
            ->and($activities->first()->body)->toBe('Second comment', 'newest activity should appear first when sort is desc')
            ->and($activities->last()->body)->toBe('First comment');
    });

    it('defaults to asc when no sort direction is given and user has no preference', function (): void {
        $user = User::factory()->create(['activity_sort_order' => null]);

        $this->actingAs($user);

        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'First comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Second comment',
            'created_at'        => now()->subMinutes(5),
        ]);

        $activities = $task->getActivityFeed();

        expect($activities->first()->body)->toBe('First comment', 'should default to asc order when user has no preference');
    });

    it('uses the authenticated user preference when no sort direction is explicitly given', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);

        $this->actingAs($user);

        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'First comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Second comment',
            'created_at'        => now()->subMinutes(5),
        ]);

        $activities = $task->getActivityFeed();

        expect($activities->first()->body)->toBe('Second comment', 'should use desc order when user preference is desc');
    });

    it('explicit sort direction overrides the user preference', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);

        $this->actingAs($user);

        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'First comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Second comment',
            'created_at'        => now()->subMinutes(5),
        ]);

        $activities = $task->getActivityFeed('asc');

        expect($activities->first()->body)->toBe('First comment', 'explicit asc should override user desc preference');
    });
});
