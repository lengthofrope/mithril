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

describe('CleanOrphanedAttachments command', function (): void {
    it('deletes attachments without an associated activity', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/orphan.pdf', 'orphan content');

        $user = User::factory()->create();

        $attachment = Attachment::create([
            'user_id' => $user->id,
            'activity_id' => 0,
            'filename' => 'orphan.pdf',
            'path' => 'attachments/orphan.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->artisan('attachments:clean-orphaned')
            ->assertSuccessful();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing('attachments/orphan.pdf');
    });

    it('does not delete attachments with a valid activity', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/valid.pdf', 'valid content');

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
            'filename' => 'valid.pdf',
            'path' => 'attachments/valid.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->artisan('attachments:clean-orphaned')
            ->assertSuccessful();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists('attachments/valid.pdf');
    });

    it('outputs count of cleaned attachments', function (): void {
        $this->artisan('attachments:clean-orphaned')
            ->assertSuccessful()
            ->expectsOutputToContain('0');
    });
});
