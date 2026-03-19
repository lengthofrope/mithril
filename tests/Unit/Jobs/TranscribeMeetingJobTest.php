<?php

declare(strict_types=1);

use App\Enums\TranscriptionStatus;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\User;
use App\Services\Transcription\TranscriptionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('TranscribeMeetingJob', function (): void {
    describe('findOrCreateTranscription', function (): void {
        it('creates a new pending transcription record when none exists', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Transcribed text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'nl']);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(0);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(1);
        });

        it('uses the meeting transcription_language when creating a new record', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Transcribed text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'en']);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->language)->toBe('en');
        });

        it('uses the configured provider name when creating a new record', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');
            Config::set('meetings.transcription.provider', 'whisper');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Transcribed text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->provider)->toBe('whisper');
        });

        it('reuses an existing transcription record instead of creating a new one', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Updated text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->pending()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(1);
        });
    });

    describe('handle — success path', function (): void {
        it('updates the transcription status to processing before calling the service', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $capturedStatus = null;

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturnUsing(function () use (&$capturedStatus): string {
                $capturedStatus = MeetingTranscription::withoutGlobalScopes()->first()?->status;

                return 'Transcribed text';
            });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            expect($capturedStatus)->toBe(TranscriptionStatus::Processing);
        });

        it('updates the transcription status to completed on success', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Transcribed text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->status)->toBe(TranscriptionStatus::Completed);
        });

        it('saves the transcribed text as content on success', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('This is the full transcript.');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->content)->toBe('This is the full transcript.');
        });

        it('appends new transcription text to existing content', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/second.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Second part.');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/second.webm',
            ]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'First part.',
                'status' => TranscriptionStatus::Completed,
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->content)->toContain('First part.')
                ->and($transcription->content)->toContain('Second part.');
        });

        it('clears the error_message on success', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturn('Transcribed text');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);
            MeetingTranscription::factory()->failed()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->error_message)->toBeNull();
        });

        it('passes the meeting transcription_language to the service', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $capturedLanguage = null;

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andReturnUsing(
                function (string $path, string $language) use (&$capturedLanguage): string {
                    $capturedLanguage = $language;

                    return 'Transcribed text';
                }
            );

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'transcription_language' => 'nl']);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            expect($capturedLanguage)->toBe('nl');
        });
    });

    describe('handle — failure path', function (): void {
        it('updates the transcription status to failed when the service throws', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andThrow(new \RuntimeException('Service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->status)->toBe(TranscriptionStatus::Failed);
        });

        it('saves the exception message as error_message on failure', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andThrow(new \RuntimeException('Service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

            $transcription = MeetingTranscription::withoutGlobalScopes()->first();
            expect($transcription->error_message)->toBe('Service unavailable');
        });

        it('does not throw when the transcription service fails', function (): void {
            Storage::fake('local');
            Storage::disk('local')->put('recordings/test.webm', 'fake audio');

            $mock = $this->mock(TranscriptionServiceInterface::class);
            $mock->shouldReceive('transcribe')->once()->andThrow(new \RuntimeException('Service unavailable'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $recording = MeetingRecording::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => 'recordings/test.webm',
            ]);

            expect(fn () => (new TranscribeMeetingJob($meeting, $recording))->handle($mock))->not->toThrow(\Throwable::class);
        });
    });
});
