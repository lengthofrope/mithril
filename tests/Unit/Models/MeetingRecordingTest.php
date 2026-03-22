<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MeetingRecording model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(MeetingRecording::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $recording = MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_test.webm',
                'original_filename' => 'session.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 204800,
                'duration_seconds' => 90,
            ]);

            expect($recording->meeting_id)->toBe($meeting->id)
                ->and($recording->disk)->toBe('local')
                ->and($recording->path)->toBe('recordings/2026/03/rec_test.webm')
                ->and($recording->original_filename)->toBe('session.webm')
                ->and($recording->mime_type)->toBe('audio/webm')
                ->and($recording->size_bytes)->toBe(204800)
                ->and($recording->duration_seconds)->toBe(90);
        });

        it('allows null for optional fields original_filename and duration_seconds', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $recording = MeetingRecording::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'disk' => 'local',
                'path' => 'recordings/2026/03/rec_minimal.webm',
                'mime_type' => 'audio/webm',
                'size_bytes' => 1024,
                'original_filename' => null,
                'duration_seconds' => null,
            ]);

            expect($recording->original_filename)->toBeNull()
                ->and($recording->duration_seconds)->toBeNull();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Meeting', function (): void {
            $meeting = Meeting::factory()->create();
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->user_id,
            ]);

            expect($recording->meeting())->toBeInstanceOf(BelongsTo::class)
                ->and($recording->meeting->id)->toBe($meeting->id);
        });

        it('does not return recordings belonging to other meetings', function (): void {
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);

            MeetingRecording::factory()->create(['meeting_id' => $meetingA->id, 'user_id' => $user->id]);
            MeetingRecording::factory()->create(['meeting_id' => $meetingB->id, 'user_id' => $user->id]);

            expect($meetingA->recordings)->toHaveCount(1)
                ->and($meetingA->recordings->first()->meeting_id)->toBe($meetingA->id);
        });
    });
});
