<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('ActivityController', function (): void {
    describe('store (POST /api/v1/{type}/{id}/activities)', function (): void {
        it('creates a comment activity', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'comment',
                'body' => 'This is a comment',
            ]);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('activities', [
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => 'comment',
                'body' => 'This is a comment',
            ]);
        });

        it('creates a link activity', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'link',
                'url' => 'https://example.com',
                'link_title' => 'Example Site',
                'body' => 'Check this out',
            ]);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $activity = Activity::latest()->first();
            expect($activity->type)->toBe(ActivityType::Link)
                ->and($activity->metadata['url'])->toBe('https://example.com')
                ->and($activity->metadata['title'])->toBe('Example Site');
        });

        it('creates an attachment activity with file upload', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'attachment',
                'files' => [UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf')],
            ]);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('activities', [
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => 'attachment',
            ]);

            expect(Attachment::count())->toBe(1);
            $attachment = Attachment::first();
            expect($attachment->filename)->toBe('document.pdf')
                ->and($attachment->mime_type)->toBe('application/pdf');
        });

        it('supports multiple file uploads (max 5)', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $files = [];
            for ($i = 0; $i < 3; $i++) {
                $files[] = UploadedFile::fake()->create("file{$i}.pdf", 100);
            }

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'attachment',
                'files' => $files,
            ]);

            $response->assertStatus(201);
            expect(Attachment::count())->toBe(3);
        });

        it('rejects more than 5 files', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $files = [];
            for ($i = 0; $i < 6; $i++) {
                $files[] = UploadedFile::fake()->create("file{$i}.pdf", 100);
            }

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'attachment',
                'files' => $files,
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['files']);
        });

        it('rejects files larger than 10MB', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'attachment',
                'files' => [UploadedFile::fake()->create('large.pdf', 11000)],
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['files.0']);
        });

        it('validates body max length', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'comment',
                'body' => str_repeat('a', 10001),
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['body']);
        });

        it('validates url format for link type', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'link',
                'url' => 'not-a-url',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['url']);
        });

        it('works with follow-up parent type', function (): void {
            $user = User::factory()->create();
            $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/follow-ups/{$followUp->id}/activities", [
                'type' => 'comment',
                'body' => 'Comment on follow-up',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('activities', [
                'activityable_type' => FollowUp::class,
                'activityable_id' => $followUp->id,
            ]);
        });

        it('works with note parent type', function (): void {
            $user = User::factory()->create();
            $note = Note::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/notes/{$note->id}/activities", [
                'type' => 'comment',
                'body' => 'Comment on note',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('activities', [
                'activityable_type' => Note::class,
                'activityable_id' => $note->id,
            ]);
        });

        it('works with bila parent type', function (): void {
            $user = User::factory()->create();
            $bila = Bila::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/bilas/{$bila->id}/activities", [
                'type' => 'comment',
                'body' => 'Comment on bila',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('activities', [
                'activityable_type' => Bila::class,
                'activityable_id' => $bila->id,
            ]);
        });

        it('returns 404 for invalid parent type', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/invalid/1/activities', [
                'type' => 'comment',
                'body' => 'Test',
            ]);

            $response->assertNotFound();
        });

        it('returns 404 for parent resource of another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'comment',
                'body' => 'Should not work',
            ]);

            $response->assertNotFound();
        });

        it('rejects upload when user storage quota would be exceeded', function (): void {
            Storage::fake('local');
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $existingActivity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Attachment,
            ]);

            Attachment::create([
                'user_id' => $user->id,
                'activity_id' => $existingActivity->id,
                'filename' => 'big.bin',
                'path' => 'attachments/big.bin',
                'disk' => 'local',
                'mime_type' => 'application/octet-stream',
                'size' => 1073741824,
            ]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'attachment',
                'files' => [UploadedFile::fake()->create('extra.pdf', 1024)],
            ]);

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns standard ApiResponse format', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/activities", [
                'type' => 'comment',
                'body' => 'Test format',
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data',
                ]);
        });
    });

    describe('update (PATCH /api/v1/{type}/{id}/activities/{activity})', function (): void {
        it('updates the body of an activity', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'Original',
            ]);

            $response = $this->patchJson("/api/v1/tasks/{$task->id}/activities/{$activity->id}", [
                'body' => 'Updated',
            ]);

            $response->assertOk()
                ->assertJson(['success' => true]);

            expect($activity->fresh()->body)->toBe('Updated');
        });

        it('returns 404 when activity does not belong to parent', function (): void {
            $user = User::factory()->create();
            $task1 = Task::factory()->create(['user_id' => $user->id]);
            $task2 = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task1->id,
                'type' => ActivityType::Comment,
                'body' => 'On task 1',
            ]);

            $response = $this->patchJson("/api/v1/tasks/{$task2->id}/activities/{$activity->id}", [
                'body' => 'Hacked',
            ]);

            $response->assertNotFound();
        });
    });

    describe('destroy (DELETE /api/v1/{type}/{id}/activities/{activity})', function (): void {
        it('deletes an activity', function (): void {
            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);

            $activity = Activity::create([
                'user_id' => $user->id,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
                'type' => ActivityType::Comment,
                'body' => 'To delete',
            ]);

            $response = $this->deleteJson("/api/v1/tasks/{$task->id}/activities/{$activity->id}");

            $response->assertOk()
                ->assertJson(['success' => true]);

            $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        });

        it('cascades deletion to attachments and their files', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('attachments/2026/03/test.pdf', 'fake');

            $user = User::factory()->create();
            $task = Task::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user);

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

            $response = $this->deleteJson("/api/v1/tasks/{$task->id}/activities/{$activity->id}");

            $response->assertOk();
            $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
            Storage::disk('local')->assertMissing('attachments/2026/03/test.pdf');
        });
    });

    describe('route constraints', function (): void {
        it('constrains type parameter to valid model types', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/api/v1/users/1/activities', [
                'type' => 'comment',
                'body' => 'Nope',
            ]);

            $response->assertNotFound();
        });
    });

    describe('authentication', function (): void {
        it('returns 401 for unauthenticated requests', function (): void {
            $response = $this->postJson('/api/v1/tasks/1/activities', [
                'type' => 'comment',
                'body' => 'Not logged in',
            ]);

            $response->assertUnauthorized();
        });
    });
});
