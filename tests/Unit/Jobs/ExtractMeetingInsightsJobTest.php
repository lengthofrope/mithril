<?php

declare(strict_types=1);

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Jobs\ExtractMeetingInsightsJob;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Models\MeetingTranscription;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MeetingInsights\ExtractionItem;
use App\Services\MeetingInsights\ExtractionResult;
use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ExtractMeetingInsightsJob', function (): void {
    describe('handle — success path', function (): void {
        it('calls the extractor with the transcription content', function (): void {
            $capturedTranscription = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription) use (&$capturedTranscription): ExtractionResult {
                    $capturedTranscription = $transcription;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'content' => 'This is the transcription text.',
            ]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($capturedTranscription)->toBe('This is the transcription text.');
        });

        it('calls the extractor with the meeting title', function (): void {
            $capturedTitle = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription, array $attendees, string $meetingTitle) use (&$capturedTitle): ExtractionResult {
                    $capturedTitle = $meetingTitle;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Q1 Planning Session']);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($capturedTitle)->toBe('Q1 Planning Session');
        });

        it('calls the extractor with attendees mapped to id and name', function (): void {
            $capturedAttendees = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription, array $attendees) use (&$capturedAttendees): ExtractionResult {
                    $capturedAttendees = $attendees;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id, 'name' => 'Alice']);
            $meeting->attendees()->attach($member);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting->fresh()))->handle($mock);

            expect($capturedAttendees)->toHaveCount(1)
                ->and($capturedAttendees[0]['id'])->toBe($member->id)
                ->and($capturedAttendees[0]['name'])->toBe('Alice');
        });

        it('saves the AI-generated summary to the meeting', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(summary: 'AI-generated summary text.', items: []));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($meeting->fresh()->summary)->toBe('AI-generated summary text.');
        });

        it('creates a MeetingExtraction record for each extracted item', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(
                    summary: 'Summary.',
                    items: [
                        new ExtractionItem(type: 'task', content: 'Do something'),
                        new ExtractionItem(type: 'follow_up', content: 'Check progress'),
                    ],
                ));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect(MeetingExtraction::withoutGlobalScopes()->count())->toBe(2);
        });

        it('creates extraction records with pending status', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(
                    summary: 'Summary.',
                    items: [
                        new ExtractionItem(type: 'task', content: 'Do something'),
                    ],
                ));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            $extraction = MeetingExtraction::withoutGlobalScopes()->first();
            expect($extraction->status)->toBe(ExtractionStatus::Pending);
        });

        it('stores the correct type and content on each extraction record', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(
                    summary: 'Summary.',
                    items: [
                        new ExtractionItem(type: 'task', content: 'Send the report.'),
                        new ExtractionItem(type: 'follow_up', content: 'Check in next week.'),
                    ],
                ));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            $extractions = MeetingExtraction::withoutGlobalScopes()->orderBy('id')->get();
            expect($extractions[0]->type)->toBe(ExtractionType::Task)
                ->and($extractions[0]->content)->toBe('Send the report.')
                ->and($extractions[1]->type)->toBe(ExtractionType::FollowUp)
                ->and($extractions[1]->content)->toBe('Check in next week.');
        });

        it('stores assignee_id, priority, and deadline from extracted items', function (): void {
            $user = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(
                    summary: 'Summary.',
                    items: [
                        new ExtractionItem(
                            type: 'task',
                            content: 'Do something',
                            assigneeId: $member->id,
                            priority: 'high',
                            deadline: '2026-06-01',
                        ),
                    ],
                ));

            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            $extraction = MeetingExtraction::withoutGlobalScopes()->first();
            expect($extraction->assignee_id)->toBe($member->id)
                ->and($extraction->priority)->toBe('high')
                ->and($extraction->deadline->toDateString())->toBe('2026-06-01');
        });

        it('associates extraction records with the correct meeting and user', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturn(new ExtractionResult(
                    summary: 'Summary.',
                    items: [
                        new ExtractionItem(type: 'task', content: 'Something to do'),
                    ],
                ));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            $extraction = MeetingExtraction::withoutGlobalScopes()->first();
            expect($extraction->meeting_id)->toBe($meeting->id)
                ->and($extraction->user_id)->toBe($user->id);
        });
    });

    describe('handle — language resolution', function (): void {
        it('uses meeting output_language when set', function (): void {
            $capturedLanguage = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription, array $attendees, string $meetingTitle, string $language) use (&$capturedLanguage): ExtractionResult {
                    $capturedLanguage = $language;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create(['preferred_output_language' => 'nl']);
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'output_language' => 'en']);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($capturedLanguage)->toBe('en');
        });

        it('falls back to user preferred_output_language when meeting output_language is null', function (): void {
            $capturedLanguage = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription, array $attendees, string $meetingTitle, string $language) use (&$capturedLanguage): ExtractionResult {
                    $capturedLanguage = $language;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create(['preferred_output_language' => 'fr']);
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'output_language' => null]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($capturedLanguage)->toBe('fr');
        });

        it("falls back to 'nl' when meeting output_language is null and user relationship is not loaded", function (): void {
            $capturedLanguage = null;

            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andReturnUsing(function (string $transcription, array $attendees, string $meetingTitle, string $language) use (&$capturedLanguage): ExtractionResult {
                    $capturedLanguage = $language;

                    return new ExtractionResult(summary: 'Summary.', items: []);
                });

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'output_language' => null]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            // Unset the user relationship so the null-safe call $meeting->user?->preferred_output_language returns null
            $meetingWithoutUser = Meeting::find($meeting->id);
            $meetingWithoutUser->setRelation('user', null);

            (new ExtractMeetingInsightsJob($meetingWithoutUser))->handle($mock);

            expect($capturedLanguage)->toBe('nl');
        });
    });

    describe('handle — skip conditions', function (): void {
        it('skips extraction when no transcription exists for the meeting', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldNotReceive('extract');

            $meeting = Meeting::factory()->create();

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect(MeetingExtraction::withoutGlobalScopes()->count())->toBe(0);
        });

        it('skips extraction when the transcription content is null', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldNotReceive('extract');

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->pending()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect(MeetingExtraction::withoutGlobalScopes()->count())->toBe(0);
        });

        it('does not update the meeting summary when transcription is missing', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldNotReceive('extract');

            $meeting = Meeting::factory()->create(['summary' => null]);

            (new ExtractMeetingInsightsJob($meeting))->handle($mock);

            expect($meeting->fresh()->summary)->toBeNull();
        });
    });

    describe('handle — error handling', function (): void {
        it('rethrows exceptions so the queue can retry the job', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andThrow(new \RuntimeException('OpenAI API error'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            expect(fn () => (new ExtractMeetingInsightsJob($meeting))->handle($mock))
                ->toThrow(\RuntimeException::class, 'OpenAI API error');
        });

        it('does not persist partial extraction records when the extractor throws', function (): void {
            $mock = $this->mock(MeetingInsightExtractorInterface::class);
            $mock->shouldReceive('extract')
                ->once()
                ->andThrow(new \RuntimeException('API failure'));

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            try {
                (new ExtractMeetingInsightsJob($meeting))->handle($mock);
            } catch (\RuntimeException) {
            }

            expect(MeetingExtraction::withoutGlobalScopes()->count())->toBe(0);
        });
    });
});
