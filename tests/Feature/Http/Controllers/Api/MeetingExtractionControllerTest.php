<?php

declare(strict_types=1);

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Jobs\ExtractMeetingInsightsJob;
use App\Models\Agreement;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Models\MeetingTranscription;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('MeetingExtractionController', function (): void {
    describe('index (GET /api/v1/meetings/{meeting}/extractions)', function (): void {
        it('returns all extractions for the authenticated user\'s meeting', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::FollowUp]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/extractions"
            );

            $response->assertOk()
                ->assertJson(['success' => true])
                ->assertJsonCount(2, 'data.extractions');
        });

        it('returns extractions ordered by id ascending', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $first = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            $second = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/extractions"
            );

            $response->assertOk();
            $ids = collect($response->json('data.extractions'))->pluck('id')->all();
            expect($ids)->toBe([$first->id, $second->id]);
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->getJson(
                "/api/v1/meetings/{$meeting->id}/extractions"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $this->getJson("/api/v1/meetings/{$meeting->id}/extractions")
                ->assertUnauthorized();
        });
    });

    describe('accept (POST /api/v1/meetings/{meeting}/extractions/{extraction}/accept)', function (): void {
        it('creates a Task for a task-type extraction', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'content' => 'Write the quarterly report.',
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $this->assertDatabaseHas('tasks', ['title' => 'Write the quarterly report.']);
        });

        it('creates a FollowUp for a follow_up-type extraction', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::FollowUp,
                'content' => 'Check on project status.',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $this->assertDatabaseHas('follow_ups', ['title' => 'Check on project status.']);
        });

        it('creates an Agreement for an agreement-type extraction', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Agreement,
                'content' => 'Deploy to production on Friday.',
                'assignee_id' => $member->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $this->assertDatabaseHas('agreements', ['description' => 'Deploy to production on Friday.']);
        });

        it('creates an Agreement for a decision-type extraction', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Decision,
                'content' => 'Use TypeScript for all new modules.',
                'assignee_id' => $member->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $this->assertDatabaseHas('agreements', ['description' => 'Use TypeScript for all new modules.']);
        });

        it('sets extraction status to accepted when no overrides are provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Accepted);
        });

        it('links the created resource on the extraction record', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $fresh = $extraction->fresh();
            expect($fresh->created_model_type)->toBe(Task::class)
                ->and($fresh->created_model_id)->not->toBeNull();
        });

        it('sets status to modified when a content override is provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'content' => 'Original content.',
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept",
                ['content' => 'Revised content.']
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Modified);
        });

        it('updates the content on the extraction when an override is provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'content' => 'Original content.',
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept",
                ['content' => 'Revised content.']
            );

            expect($extraction->fresh()->content)->toBe('Revised content.');
        });

        it('sets status to modified when an assignee override is provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept",
                ['assignee_id' => $member->id]
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Modified);
        });

        it('sets status to modified when a priority override is provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept",
                ['priority' => 'urgent']
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Modified);
        });

        it('sets status to modified when a deadline override is provided', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'priority' => 'normal',
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept",
                ['deadline' => '2026-06-01']
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Modified);
        });

        it('returns 422 when the extraction has already been accepted', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->accepted()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns 422 when the extraction has already been rejected', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'status' => ExtractionStatus::Rejected,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns 404 when the extraction belongs to a different meeting', function (): void {
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meetingB->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meetingA->id}/extractions/{$extraction->id}/accept"
            );

            $response->assertNotFound();
        });

        it('returns a success response on acceptance', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'priority' => 'normal',
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();
            $extraction = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $meeting->user_id]);

            $this->postJson("/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/accept")
                ->assertUnauthorized();
        });
    });

    describe('reject (POST /api/v1/meetings/{meeting}/extractions/{extraction}/reject)', function (): void {
        it('sets extraction status to rejected', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/reject"
            );

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Rejected);
        });

        it('returns a success response on rejection', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/reject"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 404 when the extraction belongs to a different meeting', function (): void {
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meetingB->id,
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meetingA->id}/extractions/{$extraction->id}/reject"
            );

            $response->assertNotFound();
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $otherUser->id,
            ]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/reject"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();
            $extraction = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $meeting->user_id]);

            $this->postJson("/api/v1/meetings/{$meeting->id}/extractions/{$extraction->id}/reject")
                ->assertUnauthorized();
        });
    });

    describe('bulk (POST /api/v1/meetings/{meeting}/extractions/bulk)', function (): void {
        it('creates resources for all selected pending extractions on bulk accept', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $task = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task, 'priority' => 'normal']);
            $followUp = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::FollowUp]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'accept',
                    'extraction_ids' => [$task->id, $followUp->id],
                ]
            );

            expect(Task::count())->toBe(1)
                ->and(FollowUp::count())->toBe(1);
        });

        it('sets all selected extractions to accepted on bulk accept', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);
            $extractionA = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task, 'priority' => 'normal']);
            $extractionB = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Agreement, 'assignee_id' => $member->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'accept',
                    'extraction_ids' => [$extractionA->id, $extractionB->id],
                ]
            );

            expect($extractionA->fresh()->status)->toBe(ExtractionStatus::Accepted)
                ->and($extractionB->fresh()->status)->toBe(ExtractionStatus::Accepted);
        });

        it('sets all selected extractions to rejected on bulk reject', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extractionA = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            $extractionB = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'reject',
                    'extraction_ids' => [$extractionA->id, $extractionB->id],
                ]
            );

            expect($extractionA->fresh()->status)->toBe(ExtractionStatus::Rejected)
                ->and($extractionB->fresh()->status)->toBe(ExtractionStatus::Rejected);
        });

        it('does not create resources on bulk reject', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $extraction = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'reject',
                    'extraction_ids' => [$extraction->id],
                ]
            );

            expect(Task::count())->toBe(0);
        });

        it('skips already-reviewed extractions in bulk operations', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $pending = MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task, 'priority' => 'normal']);
            $alreadyAccepted = MeetingExtraction::factory()->accepted()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task, 'priority' => 'normal']);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'accept',
                    'extraction_ids' => [$pending->id, $alreadyAccepted->id],
                ]
            );

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                [
                    'action' => 'accept',
                    'extraction_ids' => [$pending->id, $alreadyAccepted->id],
                ]
            );

            $response->assertJson(['data' => ['processed' => 0]]);
        });

        it('returns the count of processed extractions', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::Task]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'type' => ExtractionType::FollowUp]);
            $ids = MeetingExtraction::pluck('id')->all();

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'reject', 'extraction_ids' => $ids]
            );

            $response->assertOk()
                ->assertJson(['data' => ['processed' => 2]]);
        });

        it('returns 422 when action is missing', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['extraction_ids' => [1]]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['action']);
        });

        it('returns 422 when action is not accept or reject', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'delete', 'extraction_ids' => [1]]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['action']);
        });

        it('returns 422 when extraction_ids is missing', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'accept']
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['extraction_ids']);
        });

        it('returns 422 when extraction_ids is an empty array', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'accept', 'extraction_ids' => []]
            );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['extraction_ids']);
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'accept', 'extraction_ids' => [1]]
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $this->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/bulk",
                ['action' => 'accept', 'extraction_ids' => [1]]
            )->assertUnauthorized();
        });
    });

    describe('reExtract (POST /api/v1/meetings/{meeting}/extractions/re-extract)', function (): void {
        it('dispatches ExtractMeetingInsightsJob when a completed transcription exists', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            Queue::assertPushed(ExtractMeetingInsightsJob::class);
        });

        it('dispatches the job with the correct meeting', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            Queue::assertPushed(ExtractMeetingInsightsJob::class, function (ExtractMeetingInsightsJob $job) use ($meeting): bool {
                return $job->meeting->id === $meeting->id;
            });
        });

        it('deletes pending extractions before re-extracting', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'status' => ExtractionStatus::Pending]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'status' => ExtractionStatus::Pending]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            expect(MeetingExtraction::count())->toBe(0);
        });

        it('does not delete already-reviewed extractions on re-extract', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingExtraction::factory()->accepted()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
            MeetingExtraction::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'status' => ExtractionStatus::Pending]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            expect(MeetingExtraction::count())->toBe(1)
                ->and(MeetingExtraction::first()->status)->toBe(ExtractionStatus::Accepted);
        });

        it('returns a success response after dispatching the job', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('returns 422 when no transcription exists for the meeting', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('returns 422 when the transcription has no content', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingTranscription::factory()->pending()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        });

        it('does not dispatch a job when no transcription content is available', function (): void {
            Queue::fake();

            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            Queue::assertNotPushed(ExtractMeetingInsightsJob::class);
        });

        it('returns 404 when the meeting belongs to another user', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->postJson(
                "/api/v1/meetings/{$meeting->id}/extractions/re-extract"
            );

            $response->assertNotFound();
        });

        it('returns 401 for unauthenticated requests', function (): void {
            $meeting = Meeting::factory()->create();

            $this->postJson("/api/v1/meetings/{$meeting->id}/extractions/re-extract")
                ->assertUnauthorized();
        });
    });
});
