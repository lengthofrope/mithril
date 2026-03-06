<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\HasSortOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
describe('BilaPrepItem model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(BilaPrepItem::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'content' => 'Discuss Q3 goals',
                'is_discussed' => true,
            ]);

            expect($item->content)->toBe('Discuss Q3 goals')
                ->and($item->is_discussed)->toBeTrue();
        });
    });

    describe('casts', function (): void {
        it('casts is_discussed to boolean', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'content' => 'Item',
                'is_discussed' => true,
            ]);

            expect($item->fresh()->is_discussed)->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        it('belongs to a TeamMember', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $item = BilaPrepItem::create(['team_member_id' => $member->id, 'content' => 'Item']);

            expect($item->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($item->teamMember->id)->toBe($member->id);
        });

        it('belongs to a Bila', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $bila = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);
            $item = BilaPrepItem::create([
                'team_member_id' => $member->id,
                'bila_id' => $bila->id,
                'content' => 'Item',
            ]);

            expect($item->bila())->toBeInstanceOf(BelongsTo::class)
                ->and($item->bila->id)->toBe($bila->id);
        });

        it('allows a null bila_id (prep item not yet linked to a bila)', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $item = BilaPrepItem::create(['team_member_id' => $member->id, 'content' => 'Unlinked item']);

            expect($item->bila)->toBeNull();
        });
    });
});
