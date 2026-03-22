<?php

declare(strict_types=1);

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Models\Agreement;
use App\Models\Meeting;
use App\Models\MeetingExtraction;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('MeetingExtraction model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(MeetingExtraction::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $extraction = MeetingExtraction::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'type' => ExtractionType::Task,
                'content' => 'Send the report to the client.',
                'assignee_id' => null,
                'priority' => 'high',
                'deadline' => '2026-04-01',
                'status' => ExtractionStatus::Pending,
                'created_model_type' => null,
                'created_model_id' => null,
            ]);

            expect($extraction->meeting_id)->toBe($meeting->id)
                ->and($extraction->content)->toBe('Send the report to the client.')
                ->and($extraction->priority)->toBe('high');
        });

        it('allows null for optional fields', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $extraction = MeetingExtraction::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'type' => ExtractionType::FollowUp,
                'content' => 'Check progress next week.',
                'assignee_id' => null,
                'priority' => null,
                'deadline' => null,
                'status' => ExtractionStatus::Pending,
            ]);

            expect($extraction->assignee_id)->toBeNull()
                ->and($extraction->priority)->toBeNull()
                ->and($extraction->deadline)->toBeNull()
                ->and($extraction->created_model_type)->toBeNull()
                ->and($extraction->created_model_id)->toBeNull();
        });
    });

    describe('casts', function (): void {
        it('casts type to ExtractionType enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['type' => ExtractionType::Task]);

            expect($extraction->fresh()->type)->toBe(ExtractionType::Task);
        });

        it('casts follow_up type to ExtractionType enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['type' => ExtractionType::FollowUp]);

            expect($extraction->fresh()->type)->toBe(ExtractionType::FollowUp);
        });

        it('casts agreement type to ExtractionType enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['type' => ExtractionType::Agreement]);

            expect($extraction->fresh()->type)->toBe(ExtractionType::Agreement);
        });

        it('casts decision type to ExtractionType enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['type' => ExtractionType::Decision]);

            expect($extraction->fresh()->type)->toBe(ExtractionType::Decision);
        });

        it('casts status to ExtractionStatus enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['status' => ExtractionStatus::Pending]);

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Pending);
        });

        it('casts accepted status to ExtractionStatus enum', function (): void {
            $extraction = MeetingExtraction::factory()->accepted()->create();

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Accepted);
        });

        it('casts rejected status to ExtractionStatus enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['status' => ExtractionStatus::Rejected]);

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Rejected);
        });

        it('casts modified status to ExtractionStatus enum', function (): void {
            $extraction = MeetingExtraction::factory()->create(['status' => ExtractionStatus::Modified]);

            expect($extraction->fresh()->status)->toBe(ExtractionStatus::Modified);
        });

        it('casts deadline to a Carbon date instance', function (): void {
            $extraction = MeetingExtraction::factory()->create(['deadline' => '2026-06-15']);

            expect($extraction->fresh()->deadline)->toBeInstanceOf(Carbon::class);
        });

        it('returns null for deadline when not set', function (): void {
            $extraction = MeetingExtraction::factory()->create(['deadline' => null]);

            expect($extraction->fresh()->deadline)->toBeNull();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Meeting', function (): void {
            $meeting = Meeting::factory()->create();
            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $meeting->user_id,
            ]);

            expect($extraction->meeting())->toBeInstanceOf(BelongsTo::class)
                ->and($extraction->meeting->id)->toBe($meeting->id);
        });

        it('belongs to a TeamMember as assignee', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $extraction = MeetingExtraction::factory()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'assignee_id' => $member->id,
            ]);

            expect($extraction->assignee())->toBeInstanceOf(BelongsTo::class)
                ->and($extraction->assignee->id)->toBe($member->id);
        });

        it('returns null for assignee when assignee_id is null', function (): void {
            $extraction = MeetingExtraction::factory()->create(['assignee_id' => null]);

            expect($extraction->assignee)->toBeNull();
        });

        it('has a morphTo createdModel relationship', function (): void {
            $extraction = new MeetingExtraction();

            expect($extraction->createdModel())->toBeInstanceOf(MorphTo::class);
        });

        it('resolves createdModel to the linked Task after acceptance', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $task = Task::factory()->create(['user_id' => $user->id]);

            $extraction = MeetingExtraction::factory()->accepted()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Task,
                'created_model_type' => Task::class,
                'created_model_id' => $task->id,
            ]);

            expect($extraction->fresh()->createdModel)->toBeInstanceOf(Task::class)
                ->and($extraction->fresh()->createdModel->id)->toBe($task->id);
        });

        it('resolves createdModel to the linked Agreement after acceptance', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $agreement = Agreement::factory()->create(['user_id' => $user->id]);

            $extraction = MeetingExtraction::factory()->accepted()->create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'type' => ExtractionType::Agreement,
                'created_model_type' => Agreement::class,
                'created_model_id' => $agreement->id,
            ]);

            expect($extraction->fresh()->createdModel)->toBeInstanceOf(Agreement::class)
                ->and($extraction->fresh()->createdModel->id)->toBe($agreement->id);
        });
    });
});
