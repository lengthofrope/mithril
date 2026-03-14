<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Traits\HasActivityFeed;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

describe('HasActivityFeed trait', function (): void {
    describe('trait usage', function (): void {
        it('is used by Task model', function (): void {
            expect(in_array(HasActivityFeed::class, class_uses_recursive(Task::class)))->toBeTrue();
        });

        it('is used by FollowUp model', function (): void {
            expect(in_array(HasActivityFeed::class, class_uses_recursive(FollowUp::class)))->toBeTrue();
        });

        it('is used by Note model', function (): void {
            expect(in_array(HasActivityFeed::class, class_uses_recursive(Note::class)))->toBeTrue();
        });

        it('is used by Bila model', function (): void {
            expect(in_array(HasActivityFeed::class, class_uses_recursive(Bila::class)))->toBeTrue();
        });
    });

    describe('activities() relationship', function (): void {
        it('returns a MorphMany relationship', function (): void {
            $task = new Task();
            expect($task->activities())->toBeInstanceOf(MorphMany::class);
        });

        it('returns activities for a task', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'A comment on the task',
            ]);

            expect($task->activities)->toHaveCount(1)
                ->and($task->activities->first()->body)->toBe('A comment on the task');
        });
    });

    describe('addComment()', function (): void {
        it('creates a comment activity', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);
            $activity = $task->addComment('This is a comment');

            expect($activity)->toBeInstanceOf(Activity::class)
                ->and($activity->type)->toBe(ActivityType::Comment)
                ->and($activity->body)->toBe('This is a comment')
                ->and($activity->user_id)->toBe($user->id);
        });
    });

    describe('addLink()', function (): void {
        it('creates a link activity with url and optional title', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);
            $activity = $task->addLink('https://example.com', 'Example', 'A useful link');

            expect($activity)->toBeInstanceOf(Activity::class)
                ->and($activity->type)->toBe(ActivityType::Link)
                ->and($activity->body)->toBe('A useful link')
                ->and($activity->metadata['url'])->toBe('https://example.com')
                ->and($activity->metadata['title'])->toBe('Example');
        });

        it('creates a link activity without optional title and body', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);
            $activity = $task->addLink('https://example.com');

            expect($activity->metadata['url'])->toBe('https://example.com')
                ->and($activity->metadata['title'])->toBeNull()
                ->and($activity->body)->toBeNull();
        });
    });

    describe('logSystemEvent()', function (): void {
        it('creates a system activity with action and changes metadata', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);
            $activity = $task->logSystemEvent(
                'Status changed: open → done',
                'status_changed',
                ['status' => ['old' => 'open', 'new' => 'done']],
            );

            expect($activity->type)->toBe(ActivityType::System)
                ->and($activity->body)->toBe('Status changed: open → done')
                ->and($activity->metadata['action'])->toBe('status_changed')
                ->and($activity->metadata['changes']['status']['old'])->toBe('open');
        });
    });

    describe('getActivityFeed()', function (): void {
        it('returns activities in chronological order', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Second',
                'created_at' => now(),
            ]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'First',
                'created_at' => now()->subHour(),
            ]);

            $feed = $task->getActivityFeed();

            expect($feed)->toHaveCount(2)
                ->and($feed->first()->body)->toBe('First')
                ->and($feed->last()->body)->toBe('Second');
        });

        it('respects the limit parameter', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            for ($i = 0; $i < 5; $i++) {
                Activity::create([
                    'user_id' => $user->id,
                    'activityable_type' => Task::class,
                    'activityable_id' => $task->id,
                    'type' => ActivityType::Comment,
                    'body' => "Comment {$i}",
                ]);
            }

            $feed = $task->getActivityFeed(3);

            expect($feed)->toHaveCount(3);
        });
    });
});
