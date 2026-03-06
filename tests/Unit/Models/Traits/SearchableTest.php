<?php

declare(strict_types=1);

use App\Models\Team;
describe('Searchable', function (): void {
    describe('search scope', function (): void {
        it('returns records matching the search term in any searchable field', function (): void {
            Team::create(['name' => 'Frontend Engineers', 'description' => 'Builds the UI']);
            Team::create(['name' => 'Backend Engineers', 'description' => 'Builds the API']);
            Team::create(['name' => 'Marketing', 'description' => 'Handles campaigns']);

            $results = Team::search('Engineers')->get();

            expect($results)->toHaveCount(2);
        });

        it('returns records matching a term in the second searchable field', function (): void {
            Team::create(['name' => 'Alpha', 'description' => 'Infrastructure team']);
            Team::create(['name' => 'Beta', 'description' => 'Product team']);

            $results = Team::search('Infrastructure')->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->name)->toBe('Alpha');
        });

        it('performs partial match on the search term', function (): void {
            Team::create(['name' => 'Development Squad']);
            Team::create(['name' => 'DevOps Crew']);
            Team::create(['name' => 'Sales']);

            $results = Team::search('Dev')->get();

            expect($results)->toHaveCount(2);
        });

        it('returns all records when search term is empty string', function (): void {
            Team::create(['name' => 'Team A']);
            Team::create(['name' => 'Team B']);
            Team::create(['name' => 'Team C']);

            $results = Team::search('')->get();

            expect($results)->toHaveCount(3);
        });

        it('returns no records when term matches nothing', function (): void {
            Team::create(['name' => 'Frontend']);
            Team::create(['name' => 'Backend']);

            $results = Team::search('zzz_no_match_zzz')->get();

            expect($results)->toHaveCount(0);
        });

        it('is case-insensitive', function (): void {
            Team::create(['name' => 'Design Team']);

            $results = Team::search('design')->get();

            expect($results)->toHaveCount(1);
        });
    });
});
