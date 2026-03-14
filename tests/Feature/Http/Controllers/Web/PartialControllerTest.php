<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PartialController', function (): void {
    describe('activityFeed', function (): void {
        it('returns HTML partial for a task activity feed', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Test comment',
            ]);

            $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

            $response->assertOk()
                ->assertHeader('ETag');
        });

        it('returns 304 when ETag matches', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Test comment',
            ]);

            $firstResponse = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");
            $etag = $firstResponse->headers->get('ETag');

            $secondResponse = $this->actingAs($user)->get(
                "/partials/tasks/{$task->id}/activity-feed",
                ['If-None-Match' => $etag],
            );

            $secondResponse->assertStatus(304);
        });

        it('returns 200 with new content when data changes', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'First comment',
            ]);

            $firstResponse = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");
            $etag = $firstResponse->headers->get('ETag');

            Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Second comment',
            ]);

            $secondResponse = $this->actingAs($user)->get(
                "/partials/tasks/{$task->id}/activity-feed",
                ['If-None-Match' => $etag],
            );

            $secondResponse->assertOk()
                ->assertSee('Second comment');
        });

        it('returns 404 for resource belonging to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

            $response->assertNotFound();
        });

        it('returns 404 for invalid resource type', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/invalid/1/activity-feed');

            $response->assertNotFound();
        });
    });
});
