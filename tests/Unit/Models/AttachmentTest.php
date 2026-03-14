<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

describe('Attachment model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(Attachment::class)))->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to an activity', function (): void {
            $attachment = new Attachment();
            expect($attachment->activity())->toBeInstanceOf(BelongsTo::class);
        });
    });

    describe('helper methods', function (): void {
        it('identifies image files via isImage()', function (): void {
            $attachment = new Attachment(['mime_type' => 'image/png']);
            expect($attachment->isImage())->toBeTrue();

            $attachment = new Attachment(['mime_type' => 'image/jpeg']);
            expect($attachment->isImage())->toBeTrue();

            $attachment = new Attachment(['mime_type' => 'application/pdf']);
            expect($attachment->isImage())->toBeFalse();
        });

        it('identifies PDF files via isPdf()', function (): void {
            $attachment = new Attachment(['mime_type' => 'application/pdf']);
            expect($attachment->isPdf())->toBeTrue();

            $attachment = new Attachment(['mime_type' => 'image/png']);
            expect($attachment->isPdf())->toBeFalse();
        });

        it('formats file size in human-readable format via humanSize()', function (): void {
            expect((new Attachment(['size' => 500]))->humanSize())->toBe('500 B');
            expect((new Attachment(['size' => 1024]))->humanSize())->toBe('1 KB');
            expect((new Attachment(['size' => 1048576]))->humanSize())->toBe('1 MB');
            expect((new Attachment(['size' => 5242880]))->humanSize())->toBe('5 MB');
        });

        it('generates a signed download URL via downloadUrl()', function (): void {
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

            $url = $attachment->downloadUrl();
            expect($url)->toBeString()
                ->and($url)->toContain('signature=');
        });
    });

    describe('file deletion', function (): void {
        it('deletes the physical file when the model is deleted', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('attachments/2026/03/test.pdf', 'fake content');

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

            Storage::disk('local')->assertExists('attachments/2026/03/test.pdf');

            $attachment->delete();

            Storage::disk('local')->assertMissing('attachments/2026/03/test.pdf');
        });
    });
});
