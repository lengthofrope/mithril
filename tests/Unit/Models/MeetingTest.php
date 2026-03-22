<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

describe('Meeting model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of core fields', function (): void {
            $user = User::factory()->create();

            $meeting = Meeting::create([
                'user_id' => $user->id,
                'title' => 'Weekly Sync',
                'type' => MeetingType::Team,
                'status' => MeetingStatus::Scheduled,
                'scheduled_at' => '2026-04-01 10:00:00',
                'notes' => 'Agenda notes',
                'transcription_language' => 'en',
            ]);

            expect($meeting->title)->toBe('Weekly Sync')
                ->and($meeting->notes)->toBe('Agenda notes')
                ->and($meeting->transcription_language)->toBe('en');
        });
    });

    describe('casts', function (): void {
        it('casts type to MeetingType enum', function (): void {
            $meeting = Meeting::factory()->create(['type' => MeetingType::OneOnOne]);

            expect($meeting->fresh()->type)->toBe(MeetingType::OneOnOne);
        });

        it('casts status to MeetingStatus enum', function (): void {
            $meeting = Meeting::factory()->create(['status' => MeetingStatus::Scheduled]);

            expect($meeting->fresh()->status)->toBe(MeetingStatus::Scheduled);
        });

        it('casts scheduled_at to a Carbon datetime instance', function (): void {
            $meeting = Meeting::factory()->create(['scheduled_at' => '2026-04-01 10:00:00']);

            expect($meeting->fresh()->scheduled_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts started_at and ended_at to Carbon datetime instances', function (): void {
            $meeting = Meeting::factory()->create([
                'started_at' => '2026-04-01 10:05:00',
                'ended_at' => '2026-04-01 11:00:00',
            ]);

            $fresh = $meeting->fresh();
            expect($fresh->started_at)->toBeInstanceOf(Carbon::class)
                ->and($fresh->ended_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts is_done to boolean', function (): void {
            $meeting = Meeting::factory()->create(['is_done' => true]);

            expect($meeting->fresh()->is_done)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('optionally belongs to a Team', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

            expect($meeting->team())->toBeInstanceOf(BelongsTo::class)
                ->and($meeting->team->id)->toBe($team->id);
        });

        it('allows null team_id', function (): void {
            $meeting = Meeting::factory()->create(['team_id' => null]);

            expect($meeting->team)->toBeNull();
        });

        it('has a belongsToMany relationship to TeamMember through attendees', function (): void {
            $meeting = Meeting::factory()->create();

            expect($meeting->attendees())->toBeInstanceOf(BelongsToMany::class);
        });

        it('returns attached attendees', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member1 = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $member2 = TeamMember::create(['team_id' => $team->id, 'name' => 'Bob', 'user_id' => $user->id]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $meeting->attendees()->attach([$member1->id, $member2->id]);

            expect($meeting->attendees)->toHaveCount(2);
        });

        it('has a hasMany relationship to MeetingPrepItem', function (): void {
            $meeting = Meeting::factory()->create();

            expect($meeting->prepItems())->toBeInstanceOf(HasMany::class);
        });

        it('returns related prep items', function (): void {
            $meeting = Meeting::factory()->create();
            MeetingPrepItem::factory()->count(2)->create(['meeting_id' => $meeting->id, 'user_id' => $meeting->user_id]);

            expect($meeting->prepItems)->toHaveCount(2);
        });

        it('does not include prep items from other meetings', function (): void {
            $user = User::factory()->create();
            $meetingA = Meeting::factory()->create(['user_id' => $user->id]);
            $meetingB = Meeting::factory()->create(['user_id' => $user->id]);
            MeetingPrepItem::factory()->create(['meeting_id' => $meetingA->id, 'user_id' => $user->id, 'content' => 'For A']);
            MeetingPrepItem::factory()->create(['meeting_id' => $meetingB->id, 'user_id' => $user->id, 'content' => 'For B']);

            expect($meetingA->prepItems)->toHaveCount(1)
                ->and($meetingA->prepItems->first()->content)->toBe('For A');
        });
    });

    describe('filterable', function (): void {
        it('filters by team_id', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            Meeting::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
            Meeting::factory()->create(['user_id' => $user->id, 'team_id' => null]);

            $results = Meeting::applyFilters(['team_id' => $team->id])->get();

            expect($results)->toHaveCount(1);
        });

        it('filters by type', function (): void {
            $user = User::factory()->create();
            Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::OneOnOne]);
            Meeting::factory()->create(['user_id' => $user->id, 'type' => MeetingType::Team]);

            $results = Meeting::applyFilters(['type' => 'one_on_one'])->get();

            expect($results)->toHaveCount(1);
        });
    });

    describe('searchable', function (): void {
        it('searches by title', function (): void {
            $user = User::factory()->create();
            Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Sprint Retro']);
            Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Weekly Sync']);

            $results = Meeting::search('Retro')->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Sprint Retro');
        });
    });
});
