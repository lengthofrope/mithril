<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Activity feed sort preference', function (): void {
    it('renders activities in asc order when user preference is asc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'asc']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Oldest comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Newest comment',
            'created_at'        => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

        $response->assertOk();

        $content = $response->getContent();
        $oldestPos = strpos($content, 'Oldest comment');
        $newestPos = strpos($content, 'Newest comment');

        expect($oldestPos)->toBeLessThan($newestPos, 'oldest comment should appear before newest when user preference is asc');
    });

    it('renders activities in desc order when user preference is desc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Oldest comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Newest comment',
            'created_at'        => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

        $response->assertOk();

        $content = $response->getContent();
        $oldestPos = strpos($content, 'Oldest comment');
        $newestPos = strpos($content, 'Newest comment');

        expect($newestPos)->toBeLessThan($oldestPos, 'newest comment should appear before oldest when user preference is desc');
    });

    it('defaults to asc order when user has no activity sort preference', function (): void {
        $user = User::factory()->create(['activity_sort_order' => null]);
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Oldest comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Newest comment',
            'created_at'        => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

        $response->assertOk();

        $content = $response->getContent();
        $oldestPos = strpos($content, 'Oldest comment');
        $newestPos = strpos($content, 'Newest comment');

        expect($oldestPos)->toBeLessThan($newestPos, 'should default to asc (oldest first) when user has no preference');
    });

    it('persists the activity sort order preference via the auto-save endpoint', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'asc']);

        $response = $this->actingAs($user)->post('/api/v1/auto-save', [
            'model' => 'user',
            'id'    => $user->id,
            'field' => 'activity_sort_order',
            'value' => 'desc',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        expect($user->fresh()->activity_sort_order)->toBe('desc', 'activity_sort_order should be persisted to the database');
    });

    it('changing preference to desc causes feed to render newest-first on next load', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'asc']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Oldest comment',
            'created_at'        => now()->subMinutes(10),
        ]);

        Activity::create([
            'user_id'           => $user->id,
            'activityable_type' => Task::class,
            'activityable_id'   => $task->id,
            'type'              => ActivityType::Comment,
            'body'              => 'Newest comment',
            'created_at'        => now()->subMinutes(1),
        ]);

        $this->actingAs($user)->post('/api/v1/auto-save', [
            'model' => 'user',
            'id'    => $user->id,
            'field' => 'activity_sort_order',
            'value' => 'desc',
        ]);

        $user->refresh();

        $response = $this->actingAs($user)->get("/partials/tasks/{$task->id}/activity-feed");

        $response->assertOk();

        $content = $response->getContent();
        $oldestPos = strpos($content, 'Oldest comment');
        $newestPos = strpos($content, 'Newest comment');

        expect($newestPos)->toBeLessThan($oldestPos, 'feed should render newest-first after preference is changed to desc');
    });
});

describe('Activity feed toggle initial state on show pages', function (): void {
    it('renders the task show page with initialSortOrder desc when user preference is desc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/tasks/{$task->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'desc'", false);
    });

    it('renders the task show page with initialSortOrder asc when user preference is asc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'asc']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/tasks/{$task->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'asc'", false);
    });

    it('renders the follow-up show page with initialSortOrder desc when user preference is desc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);
        $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/follow-ups/{$followUp->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'desc'", false);
    });

    it('renders the note show page with initialSortOrder desc when user preference is desc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/notes/{$note->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'desc'", false);
    });

    it('renders the meeting show page with initialSortOrder desc when user preference is desc', function (): void {
        $user = User::factory()->create(['activity_sort_order' => 'desc']);
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/meetings/{$meeting->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'desc'", false);
    });

    it('defaults to initialSortOrder asc when user has no sort preference', function (): void {
        $user = User::factory()->create(['activity_sort_order' => null]);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/tasks/{$task->id}");

        $response->assertOk()
            ->assertSee("initialSortOrder: 'asc'", false);
    });
});
