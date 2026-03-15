<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Settings storage page', function (): void {
    it('returns 200 for authenticated user', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertOk();
    });

    it('redirects unauthenticated user to login', function (): void {
        $response = $this->get('/settings/storage');

        $response->assertRedirect('/login');
    });

    it('renders the correct view', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertViewIs('pages.settings.storage');
    });

    it('passes storage usage data to the view', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertViewHas('usedBytes');
        $response->assertViewHas('maxBytes');
        $response->assertViewHas('attachments');
        $response->assertViewHas('orphanedBytes');
        $response->assertViewHas('orphanedCount');
    });

    it('calculates correct storage usage for the user', function (): void {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $activity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'file1.pdf',
            'path' => 'attachments/2026/03/file1.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 5000,
        ]);

        Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'file2.jpg',
            'path' => 'attachments/2026/03/file2.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size' => 3000,
        ]);

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertViewHas('usedBytes', 8000);
    });

    it('does not include other users attachments in usage', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $other->id]);

        $activity = Activity::create([
            'user_id' => $other->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        Attachment::create([
            'user_id' => $other->id,
            'activity_id' => $activity->id,
            'filename' => 'other.pdf',
            'path' => 'attachments/2026/03/other.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 99999,
        ]);

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertViewHas('usedBytes', 0);
    });

    it('calculates orphaned bytes for attachments whose parent resource was deleted', function (): void {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $activity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'linked.pdf',
            'path' => 'attachments/linked.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 2000,
        ]);

        $orphanedActivity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => 99999,
            'type' => ActivityType::Attachment,
        ]);

        Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $orphanedActivity->id,
            'filename' => 'orphaned.pdf',
            'path' => 'attachments/orphaned.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 5000,
        ]);

        $response = $this->actingAs($user)->get('/settings/storage');

        $response->assertViewHas('orphanedBytes', 5000);
        $response->assertViewHas('orphanedCount', 1);
    });
});

describe('Purge orphaned attachments', function (): void {
    it('deletes all orphaned attachments and their files', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/orphaned.pdf', 'content');
        Storage::disk('local')->put('attachments/linked.pdf', 'content');

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $linkedActivity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        $linkedAttachment = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $linkedActivity->id,
            'filename' => 'linked.pdf',
            'path' => 'attachments/linked.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $orphanedActivity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => 99999,
            'type' => ActivityType::Attachment,
        ]);

        $orphanedAttachment = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $orphanedActivity->id,
            'filename' => 'orphaned.pdf',
            'path' => 'attachments/orphaned.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 5000,
        ]);

        $response = $this->actingAs($user)->post('/settings/storage/purge-orphaned');

        $response->assertRedirect(route('settings.storage'));
        expect(Attachment::find($orphanedAttachment->id))->toBeNull();
        expect(Attachment::find($linkedAttachment->id))->not->toBeNull();
        expect(Activity::find($orphanedActivity->id))->toBeNull();
        Storage::disk('local')->assertMissing('attachments/orphaned.pdf');
        Storage::disk('local')->assertExists('attachments/linked.pdf');
    });

    it('does not delete another users orphaned attachments', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $orphanedActivity = Activity::create([
            'user_id' => $other->id,
            'activityable_type' => Task::class,
            'activityable_id' => 99999,
            'type' => ActivityType::Attachment,
        ]);

        $attachment = Attachment::create([
            'user_id' => $other->id,
            'activity_id' => $orphanedActivity->id,
            'filename' => 'other.pdf',
            'path' => 'attachments/other.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($user)->post('/settings/storage/purge-orphaned');

        expect(Attachment::withoutGlobalScopes()->find($attachment->id))->not->toBeNull();
    });

    it('redirects with message when no orphaned files exist', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/storage/purge-orphaned');

        $response->assertRedirect(route('settings.storage'));
        $response->assertSessionHas('status');
    });
});

describe('Attachment deletion via settings', function (): void {
    it('deletes an attachment and its physical file', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/2026/03/file.pdf', 'content');

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $activity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        $attachment = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'file.pdf',
            'path' => 'attachments/2026/03/file.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $response = $this->actingAs($user)->delete("/api/v1/attachments/{$attachment->id}");

        $response->assertOk();
        expect(Attachment::find($attachment->id))->toBeNull();
        Storage::disk('local')->assertMissing('attachments/2026/03/file.pdf');
    });

    it('deletes the parent activity when last attachment is removed', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/2026/03/only.pdf', 'content');

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $activity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        $attachment = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'only.pdf',
            'path' => 'attachments/2026/03/only.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($user)->delete("/api/v1/attachments/{$attachment->id}");

        expect(Activity::find($activity->id))->toBeNull();
    });

    it('does not delete the parent activity when other attachments remain', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/2026/03/a.pdf', 'content');
        Storage::disk('local')->put('attachments/2026/03/b.pdf', 'content');

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $activity = Activity::create([
            'user_id' => $user->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        $attachmentA = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'a.pdf',
            'path' => 'attachments/2026/03/a.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        Attachment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'filename' => 'b.pdf',
            'path' => 'attachments/2026/03/b.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($user)->delete("/api/v1/attachments/{$attachmentA->id}");

        expect(Activity::find($activity->id))->not->toBeNull();
    });

    it('returns 404 when trying to delete another users attachment', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id]);

        $activity = Activity::create([
            'user_id' => $owner->id,
            'activityable_type' => Task::class,
            'activityable_id' => $task->id,
            'type' => ActivityType::Attachment,
        ]);

        $attachment = Attachment::create([
            'user_id' => $owner->id,
            'activity_id' => $activity->id,
            'filename' => 'secret.pdf',
            'path' => 'attachments/secret.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $response = $this->actingAs($other)->delete("/api/v1/attachments/{$attachment->id}");

        $response->assertNotFound();
    });
});
