<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

describe('Bila model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of team_member_id, scheduled_date, and notes', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            $bila = Bila::create([
                'team_member_id' => $member->id,
                'scheduled_date' => '2025-06-01',
                'notes' => 'Meeting notes here',
            ]);

            expect($bila->notes)->toBe('Meeting notes here');
        });
    });

    describe('casts', function (): void {
        it('casts scheduled_date to a Carbon date instance', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);

            $bila = Bila::create([
                'team_member_id' => $member->id,
                'scheduled_date' => '2025-06-01',
            ]);

            expect($bila->fresh()->scheduled_date)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a TeamMember', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $bila = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);

            expect($bila->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($bila->teamMember->id)->toBe($member->id);
        });

        it('has a hasMany relationship to BilaPrepItem', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $bila = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);

            expect($bila->prepItems())->toBeInstanceOf(HasMany::class);
        });

        it('returns related prep items', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $bila = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);
            BilaPrepItem::create(['team_member_id' => $member->id, 'bila_id' => $bila->id, 'content' => 'Item 1']);
            BilaPrepItem::create(['team_member_id' => $member->id, 'bila_id' => $bila->id, 'content' => 'Item 2']);

            expect($bila->prepItems)->toHaveCount(2);
        });

        it('does not include prep items from other bilas', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            $bilaA = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-06-01']);
            $bilaB = Bila::create(['team_member_id' => $member->id, 'scheduled_date' => '2025-07-01']);
            BilaPrepItem::create(['team_member_id' => $member->id, 'bila_id' => $bilaA->id, 'content' => 'For A']);
            BilaPrepItem::create(['team_member_id' => $member->id, 'bila_id' => $bilaB->id, 'content' => 'For B']);

            expect($bilaA->prepItems)->toHaveCount(1)
                ->and($bilaA->prepItems->first()->content)->toBe('For A');
        });
    });
});
