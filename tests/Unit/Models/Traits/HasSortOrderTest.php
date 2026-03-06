<?php

declare(strict_types=1);

use App\Models\Team;
describe('HasSortOrder', function (): void {
    describe('auto-assignment on creation', function (): void {
        it('assigns sort_order of 1 to the first record', function (): void {
            $team = Team::create(['name' => 'First Team']);

            expect($team->sort_order)->toBe(1);
        });

        it('auto-increments sort_order for subsequent records', function (): void {
            $first = Team::create(['name' => 'Team A']);
            $second = Team::create(['name' => 'Team B']);
            $third = Team::create(['name' => 'Team C']);

            expect($first->sort_order)->toBe(1)
                ->and($second->sort_order)->toBe(2)
                ->and($third->sort_order)->toBe(3);
        });

        it('respects an explicitly provided sort_order', function (): void {
            $team = Team::create(['name' => 'Explicit Order', 'sort_order' => 99]);

            expect($team->sort_order)->toBe(99);
        });

        it('does not override a sort_order of zero', function (): void {
            $team = Team::create(['name' => 'Zero Order', 'sort_order' => 0]);

            expect($team->sort_order)->toBe(0);
        });
    });

    describe('orderBySortOrder scope', function (): void {
        it('returns records in ascending sort_order', function (): void {
            Team::create(['name' => 'Third', 'sort_order' => 3]);
            Team::create(['name' => 'First', 'sort_order' => 1]);
            Team::create(['name' => 'Second', 'sort_order' => 2]);

            $names = Team::orderBySortOrder()->pluck('name')->toArray();

            expect($names)->toBe(['First', 'Second', 'Third']);
        });
    });

    describe('reorder static method', function (): void {
        it('updates sort_order for each item in the provided array', function (): void {
            $a = Team::create(['name' => 'A', 'sort_order' => 1]);
            $b = Team::create(['name' => 'B', 'sort_order' => 2]);
            $c = Team::create(['name' => 'C', 'sort_order' => 3]);

            Team::reorder([
                ['id' => $a->id, 'sort_order' => 3],
                ['id' => $b->id, 'sort_order' => 1],
                ['id' => $c->id, 'sort_order' => 2],
            ]);

            expect($a->fresh()->sort_order)->toBe(3)
                ->and($b->fresh()->sort_order)->toBe(1)
                ->and($c->fresh()->sort_order)->toBe(2);
        });

        it('only updates the records included in the array', function (): void {
            $a = Team::create(['name' => 'A', 'sort_order' => 1]);
            $b = Team::create(['name' => 'B', 'sort_order' => 2]);

            Team::reorder([['id' => $a->id, 'sort_order' => 10]]);

            expect($a->fresh()->sort_order)->toBe(10)
                ->and($b->fresh()->sort_order)->toBe(2);
        });
    });
});
