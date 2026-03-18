<?php

declare(strict_types=1);

use App\Enums\PrepItemType;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\HasSortOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('MeetingPrepItem model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(MeetingPrepItem::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $item = MeetingPrepItem::create([
                'user_id' => $user->id,
                'meeting_id' => $meeting->id,
                'content' => 'Discuss Q3 goals',
                'type' => PrepItemType::Question,
                'duration_minutes' => 15,
                'is_discussed' => true,
            ]);

            expect($item->content)->toBe('Discuss Q3 goals')
                ->and($item->type)->toBe(PrepItemType::Question)
                ->and($item->duration_minutes)->toBe(15)
                ->and($item->is_discussed)->toBeTrue();
        });
    });

    describe('casts', function (): void {
        it('casts type to PrepItemType enum', function (): void {
            $item = MeetingPrepItem::factory()->create(['type' => PrepItemType::Action]);

            expect($item->fresh()->type)->toBe(PrepItemType::Action);
        });

        it('casts is_discussed to boolean', function (): void {
            $item = MeetingPrepItem::factory()->create(['is_discussed' => true]);

            expect($item->fresh()->is_discussed)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Meeting', function (): void {
            $meeting = Meeting::factory()->create();
            $item = MeetingPrepItem::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $meeting->user_id]);

            expect($item->meeting())->toBeInstanceOf(BelongsTo::class)
                ->and($item->meeting->id)->toBe($meeting->id);
        });

        it('optionally belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);
            $item = MeetingPrepItem::factory()->create([
                'meeting_id' => $meeting->id,
                'team_member_id' => $member->id,
                'user_id' => $user->id,
            ]);

            expect($item->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($item->teamMember->id)->toBe($member->id);
        });

        it('allows null team_member_id', function (): void {
            $item = MeetingPrepItem::factory()->create(['team_member_id' => null]);

            expect($item->teamMember)->toBeNull();
        });
    });
});
