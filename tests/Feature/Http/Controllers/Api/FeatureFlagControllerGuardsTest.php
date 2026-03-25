<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingTranscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('MeetingRecordingController feature guard', function (): void {
    it('returns 403 when recording is disabled', function (): void {
        config()->set('meetings.recording.enabled', false);
        Storage::fake('local');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/meetings/{$meeting->id}/recordings",
            ['audio' => UploadedFile::fake()->create('recording.webm', 100, 'audio/webm')],
        );

        $response->assertStatus(403);
    });

    it('allows upload when recording is enabled', function (): void {
        config()->set('meetings.recording.enabled', true);
        Storage::fake('local');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/meetings/{$meeting->id}/recordings",
            ['audio' => UploadedFile::fake()->create('recording.webm', 100, 'audio/webm')],
        );

        $response->assertStatus(201);
    });
});

describe('MeetingExtractionController feature guard', function (): void {
    it('returns 403 on reExtract when AI is disabled', function (): void {
        config()->set('ai.enabled', false);

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/meetings/{$meeting->id}/extractions/re-extract",
        );

        $response->assertStatus(403);
    });
});
