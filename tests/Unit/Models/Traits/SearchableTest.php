<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;

describe('Searchable', function (): void {
    describe('search scope', function (): void {
        it('returns records matching the search term in any searchable field', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Frontend Engineers', 'description' => 'Builds the UI', 'user_id' => $user->id]);
            Team::create(['name' => 'Backend Engineers', 'description' => 'Builds the API', 'user_id' => $user->id]);
            Team::create(['name' => 'Marketing', 'description' => 'Handles campaigns', 'user_id' => $user->id]);

            $results = Team::search('Engineers')->get();

            expect($results)->toHaveCount(2);
        });

        it('returns records matching a term in the second searchable field', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Alpha', 'description' => 'Infrastructure team', 'user_id' => $user->id]);
            Team::create(['name' => 'Beta', 'description' => 'Product team', 'user_id' => $user->id]);

            $results = Team::search('Infrastructure')->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->name)->toBe('Alpha');
        });

        it('performs partial match on the search term', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Development Squad', 'user_id' => $user->id]);
            Team::create(['name' => 'DevOps Crew', 'user_id' => $user->id]);
            Team::create(['name' => 'Sales', 'user_id' => $user->id]);

            $results = Team::search('Dev')->get();

            expect($results)->toHaveCount(2);
        });

        it('returns all records when search term is empty string', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Team A', 'user_id' => $user->id]);
            Team::create(['name' => 'Team B', 'user_id' => $user->id]);
            Team::create(['name' => 'Team C', 'user_id' => $user->id]);

            $results = Team::search('')->get();

            expect($results)->toHaveCount(3);
        });

        it('returns no records when term matches nothing', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Frontend', 'user_id' => $user->id]);
            Team::create(['name' => 'Backend', 'user_id' => $user->id]);

            $results = Team::search('zzz_no_match_zzz')->get();

            expect($results)->toHaveCount(0);
        });

        it('is case-insensitive', function (): void {
            $user = User::factory()->create();
            Team::create(['name' => 'Design Team', 'user_id' => $user->id]);

            $results = Team::search('design')->get();

            expect($results)->toHaveCount(1);
        });
    });
});
