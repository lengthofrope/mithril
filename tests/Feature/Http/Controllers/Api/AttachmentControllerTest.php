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

describe('AttachmentController', function (): void {
    describe('download', function (): void {
        it('serves a file download via signed URL', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('attachments/2026/03/test.pdf', 'fake pdf content');

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
                'filename' => 'test.pdf',
                'path' => 'attachments/2026/03/test.pdf',
                'disk' => 'local',
                'mime_type' => 'application/pdf',
                'size' => 1024,
            ]);

            $downloadUrl = $attachment->downloadUrl();

            $response = $this->actingAs($user)->get($downloadUrl);

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        });

        it('returns 403 when signed URL is invalid', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/attachments/999/download');

            $response->assertForbidden();
        });

        it('returns 404 for attachment belonging to another user', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('attachments/test.pdf', 'content');

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
                'filename' => 'test.pdf',
                'path' => 'attachments/test.pdf',
                'disk' => 'local',
                'mime_type' => 'application/pdf',
                'size' => 1024,
            ]);

            $downloadUrl = $attachment->downloadUrl();

            $response = $this->actingAs($other)->get($downloadUrl);

            $response->assertNotFound();
        });
    });
});
