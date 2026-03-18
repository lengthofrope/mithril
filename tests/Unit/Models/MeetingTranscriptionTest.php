<?php

declare(strict_types=1);

use App\Enums\TranscriptionStatus;
use App\Models\Meeting;
use App\Models\MeetingTranscription;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MeetingTranscription model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(MeetingTranscription::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $transcription = MeetingTranscription::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'content' => 'This is the transcribed text.',
                'language' => 'en',
                'provider' => 'whisper',
                'status' => TranscriptionStatus::Completed,
                'error_message' => null,
            ]);

            expect($transcription->meeting_id)->toBe($meeting->id)
                ->and($transcription->content)->toBe('This is the transcribed text.')
                ->and($transcription->language)->toBe('en')
                ->and($transcription->provider)->toBe('whisper')
                ->and($transcription->error_message)->toBeNull();
        });

        it('allows null for optional fields content and error_message', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $transcription = MeetingTranscription::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'content' => null,
                'language' => 'nl',
                'provider' => 'whisper',
                'status' => TranscriptionStatus::Pending,
                'error_message' => null,
            ]);

            expect($transcription->content)->toBeNull()
                ->and($transcription->error_message)->toBeNull();
        });
    });

    describe('casts', function (): void {
        it('casts status to TranscriptionStatus enum', function (): void {
            $transcription = MeetingTranscription::factory()->create([
                'status' => TranscriptionStatus::Completed,
            ]);

            expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Completed);
        });

        it('casts pending status to TranscriptionStatus enum', function (): void {
            $transcription = MeetingTranscription::factory()->pending()->create();

            expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Pending);
        });

        it('casts failed status to TranscriptionStatus enum', function (): void {
            $transcription = MeetingTranscription::factory()->failed()->create();

            expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Failed);
        });

        it('casts processing status to TranscriptionStatus enum', function (): void {
            $transcription = MeetingTranscription::factory()->create([
                'status' => TranscriptionStatus::Processing,
            ]);

            expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Processing);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Meeting', function (): void {
            $meeting = Meeting::factory()->create();
            $transcription = MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->user_id,
            ]);

            expect($transcription->meeting())->toBeInstanceOf(BelongsTo::class)
                ->and($transcription->meeting->id)->toBe($meeting->id);
        });

        it('does not return transcriptions belonging to other meetings', function (): void {
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);

            MeetingTranscription::factory()->create(['meeting_id' => $meetingA->id, 'user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meetingB->id, 'user_id' => $user->id]);

            expect($meetingA->transcription->meeting_id)->toBe($meetingA->id);
        });

        it('is accessible from the meeting as a hasOne relation', function (): void {
            $meeting = Meeting::factory()->create();
            $transcription = MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->user_id,
            ]);

            expect($meeting->fresh()->transcription->id)->toBe($transcription->id);
        });
    });
});
