<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

    describe('tasksList', function (): void {
        it('excludes done tasks by default', function (): void {
            $user = User::factory()->create();
            $openTask = Task::factory()->create(['user_id' => $user->id, 'title' => 'Open task', 'status' => TaskStatus::Open]);
            $doneTask = Task::factory()->create(['user_id' => $user->id, 'title' => 'Done task', 'status' => TaskStatus::Done]);

            $response = $this->actingAs($user)->get('/partials/tasks');

            $response->assertOk()
                ->assertSee('Open task')
                ->assertDontSee('Done task');
        });

        it('includes done tasks when show_completed is set', function (): void {
            $user = User::factory()->create();
            $openTask = Task::factory()->create(['user_id' => $user->id, 'title' => 'Open task', 'status' => TaskStatus::Open]);
            $doneTask = Task::factory()->create(['user_id' => $user->id, 'title' => 'Done task', 'status' => TaskStatus::Done]);

            $response = $this->actingAs($user)->get('/partials/tasks?show_completed=1');

            $response->assertOk()
                ->assertSee('Open task')
                ->assertSee('Done task');
        });
    });

    describe('followUpsList', function (): void {
        it('sorts same-date follow-ups urgent-first in the overdue section', function (): void {
            $this->travelTo(Carbon::parse('2026-06-04 12:00:00', 'UTC'));
            $user = User::factory()->create();

            $yesterday = now()->subDay()->toDateString();

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Low priority overdue',
                'priority'       => Priority::Low,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $yesterday,
            ]);

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Urgent overdue',
                'priority'       => Priority::Urgent,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $yesterday,
            ]);

            $response = $this->actingAs($user)->get(route('partials.follow-ups'));

            $response->assertOk();
            $response->assertSeeInOrder(['Urgent overdue', 'Low priority overdue']);
        });

        it('sorts same-date follow-ups urgent-first in the today section', function (): void {
            $this->travelTo(Carbon::parse('2026-06-04 12:00:00', 'UTC'));
            $user = User::factory()->create();

            $today = now()->toDateString();

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Normal priority today',
                'priority'       => Priority::Normal,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $today,
            ]);

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Urgent today',
                'priority'       => Priority::Urgent,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $today,
            ]);

            $response = $this->actingAs($user)->get(route('partials.follow-ups'));

            $response->assertOk();
            $response->assertSeeInOrder(['Urgent today', 'Normal priority today']);
        });
    });
});
