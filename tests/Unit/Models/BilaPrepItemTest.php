<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\HasSortOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('BilaPrepItem model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(BilaPrepItem::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);

            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'content' => 'Discuss Q3 goals',
                'is_discussed' => true,
                'user_id' => $user->id,
            ]);

            expect($item->content)->toBe('Discuss Q3 goals')
                ->and($item->is_discussed)->toBeTrue();
        });
    });

    describe('casts', function (): void {
        it('casts is_discussed to boolean', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'content' => 'Item',
                'is_discussed' => true,
                'user_id' => $user->id,
            ]);

            expect($item->fresh()->is_discussed)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $item = BilaPrepItem::create(['team_member_id' => $member->id, 'content' => 'Item', 'user_id' => $user->id]);

            expect($item->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($item->teamMember->id)->toBe($member->id);
        });

        it('belongs to a Bila', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $bila = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01', 'user_id' => $user->id]);
            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'bila_id' => $bila->id,
                'content' => 'Item',
                'user_id' => $user->id,
            ]);

            expect($item->bila())->toBeInstanceOf(BelongsTo::class)
                ->and($item->bila->id)->toBe($bila->id);
        });

        it('allows a null bila_id (prep item not yet linked to a bila)', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $item = BilaPrepItem::create(['team_member_id' => $member->id, 'content' => 'Unlinked item', 'user_id' => $user->id]);

            expect($item->bila)->toBeNull();
        });
    });
});
