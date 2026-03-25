<?php

declare(strict_types=1);

use App\Jobs\DiarizeMeetingJob;
use App\Jobs\ExtractMeetingInsightsJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscription;
use App\Models\User;
use App\Services\Diarization\DiarizationServiceInterface;
use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use App\Services\Transcription\TranscriptionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('TranscribeMeetingJob feature guard', function (): void {
    it('aborts without exception when transcription is disabled', function (): void {
        config()->set('meetings.speech.server_enabled', false);
        Log::shouldReceive('info')->once()->withArgs(fn (string $msg) => str_contains($msg, 'disabled'));

        $mock = $this->mock(TranscriptionServiceInterface::class);
        $mock->shouldNotReceive('transcribe');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        $recording = MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

        expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(0);
    });

    it('proceeds normally when transcription is enabled', function (): void {
        config()->set('meetings.speech.server_enabled', true);

        $mock = $this->mock(TranscriptionServiceInterface::class);
        $mock->shouldReceive('transcribe')->once()->andReturn('Transcription text');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        $recording = MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        (new TranscribeMeetingJob($meeting, $recording))->handle($mock);

        expect(MeetingTranscription::withoutGlobalScopes()->count())->toBe(1);
    });
});

describe('DiarizeMeetingJob feature guard', function (): void {
    it('aborts without exception when transcription is disabled', function (): void {
        config()->set('meetings.speech.server_enabled', false);
        Log::shouldReceive('info')->once()->withArgs(fn (string $msg) => str_contains($msg, 'disabled'));

        $mock = $this->mock(DiarizationServiceInterface::class);
        $mock->shouldNotReceive('diarize');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        $recording = MeetingRecording::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        (new DiarizeMeetingJob($meeting, $recording))->handle($mock);
    });
});

describe('ExtractMeetingInsightsJob feature guard', function (): void {
    it('aborts without exception when AI is disabled', function (): void {
        config()->set('ai.enabled', false);
        Log::shouldReceive('info')->once()->withArgs(fn (string $msg) => str_contains($msg, 'disabled'));

        $mock = $this->mock(MeetingInsightExtractorInterface::class);
        $mock->shouldNotReceive('extract');

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        (new ExtractMeetingInsightsJob($meeting))->handle($mock);
    });

    it('proceeds normally when AI is enabled', function (): void {
        config()->set('ai.enabled', true);

        $mock = $this->mock(MeetingInsightExtractorInterface::class);
        $mock->shouldReceive('extract')->once()->andReturn(
            new \App\Services\MeetingInsights\ExtractionResult(summary: 'Summary.', items: [])
        );

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        (new ExtractMeetingInsightsJob($meeting))->handle($mock);

        expect($meeting->fresh()->summary)->toBe('Summary.');
    });
});
