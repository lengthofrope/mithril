<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('Activity model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(Activity::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Test comment',
                'metadata' => null,
            ]);

            expect($activity->body)->toBe('Test comment')
                ->and($activity->activityable_type)->toBe(Task::class)
                ->and($activity->activityable_id)->toBe($task->id);
        });
    });

    describe('casts', function (): void {
        it('casts type to ActivityType enum', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
            ]);

            expect($activity->fresh()->type)->toBe(ActivityType::Comment);
        });

        it('casts metadata to array', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Link,
                'metadata' => ['url' => 'https://example.com', 'title' => 'Example'],
            ]);

            $fresh = $activity->fresh();
            expect($fresh->metadata)->toBeArray()
                ->and($fresh->metadata['url'])->toBe('https://example.com')
                ->and($fresh->metadata['title'])->toBe('Example');
        });
    });

    describe('relationships', function (): void {
        it('has a morphTo activityable relationship', function (): void {
            $activity = new Activity();
            expect($activity->activityable())->toBeInstanceOf(MorphTo::class);
        });

        it('returns the parent task', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Hello',
            ]);

            expect($activity->activityable->id)->toBe($task->id);
        });

        it('has many attachments', function (): void {
            $activity = new Activity();
            expect($activity->attachments())->toBeInstanceOf(HasMany::class);
        });
    });

    describe('scopes', function (): void {
        it('scopes by type with ofType()', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'A comment',
            ]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::System,
                'body' => 'System event',
            ]);

            expect(Activity::ofType(ActivityType::Comment)->count())->toBe(1)
                ->and(Activity::ofType(ActivityType::System)->count())->toBe(1);
        });

        it('orders chronologically ascending with chronological()', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $first = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'First',
                'created_at' => now()->subHour(),
            ]);

            $second = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Second',
            ]);

            $results = Activity::chronological()->get();
            expect($results->first()->id)->toBe($first->id)
                ->and($results->last()->id)->toBe($second->id);
        });

        it('orders latest first with latestFirst()', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $first = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'First',
                'created_at' => now()->subHour(),
            ]);

            $second = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Second',
            ]);

            $results = Activity::latestFirst()->get();
            expect($results->first()->id)->toBe($second->id)
                ->and($results->last()->id)->toBe($first->id);
        });
    });

    describe('helper methods', function (): void {
        it('returns true for isComment() when type is comment', function (): void {
            $activity = new Activity(['type' => ActivityType::Comment]);
            expect($activity->isComment())->toBeTrue()
                ->and($activity->isLink())->toBeFalse();
        });

        it('returns true for isLink() when type is link', function (): void {
            $activity = new Activity(['type' => ActivityType::Link]);
            expect($activity->isLink())->toBeTrue()
                ->and($activity->isComment())->toBeFalse();
        });

        it('returns true for isAttachment() when type is attachment', function (): void {
            $activity = new Activity(['type' => ActivityType::Attachment]);
            expect($activity->isAttachment())->toBeTrue()
                ->and($activity->isSystem())->toBeFalse();
        });

        it('returns true for isSystem() when type is system', function (): void {
            $activity = new Activity(['type' => ActivityType::System]);
            expect($activity->isSystem())->toBeTrue()
                ->and($activity->isAttachment())->toBeFalse();
        });

        it('returns url from metadata via getUrl()', function (): void {
            $activity = new Activity([
                'type' => ActivityType::Link,
                'metadata' => ['url' => 'https://example.com'],
            ]);

            expect($activity->getUrl())->toBe('https://example.com');
        });

        it('returns null from getUrl() when no url in metadata', function (): void {
            $activity = new Activity([
                'type' => ActivityType::Comment,
                'metadata' => null,
            ]);

            expect($activity->getUrl())->toBeNull();
        });

        it('returns link title from metadata via getLinkTitle()', function (): void {
            $activity = new Activity([
                'type' => ActivityType::Link,
                'metadata' => ['url' => 'https://example.com', 'title' => 'Example Site'],
            ]);

            expect($activity->getLinkTitle())->toBe('Example Site');
        });

        it('returns null from getLinkTitle() when no title in metadata', function (): void {
            $activity = new Activity([
                'type' => ActivityType::Link,
                'metadata' => ['url' => 'https://example.com'],
            ]);

            expect($activity->getLinkTitle())->toBeNull();
        });
    });
});
