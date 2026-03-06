<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Relations\HasMany;
describe('Team model', function (): void {
    describe('traits', function (): void {
        it('uses the HasSortOrder trait', function (): void {
            expect(in_array(HasSortOrder::class, class_uses_recursive(Team::class)))->toBeTrue();
        });

        it('uses the Filterable trait', function (): void {
            expect(in_array(Filterable::class, class_uses_recursive(Team::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(Team::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of name, description, color, and sort_order', function (): void {
            $team = Team::create([
                'name' => 'Test Team',
                'description' => 'A description',
                'color' => '#ff0000',
                'sort_order' => 5,
            ]);

            expect($team->name)->toBe('Test Team')
                ->and($team->description)->toBe('A description')
                ->and($team->color)->toBe('#ff0000')
                ->and($team->sort_order)->toBe(5);
        });
    });

    describe('relationships', function (): void {
        it('has a hasMany relationship to TeamMember', function (): void {
            $team = Team::create(['name' => 'Dev Team']);

            expect($team->members())->toBeInstanceOf(HasMany::class);
        });

        it('returns related team members', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            TeamMember::create(['team_id' => $team->id, 'name' => 'Alice']);
            TeamMember::create(['team_id' => $team->id, 'name' => 'Bob']);

            expect($team->members)->toHaveCount(2);
        });

        it('does not include members from other teams', function (): void {
            $teamA = Team::create(['name' => 'Team A']);
            $teamB = Team::create(['name' => 'Team B']);
            TeamMember::create(['team_id' => $teamA->id, 'name' => 'Alice']);
            TeamMember::create(['team_id' => $teamB->id, 'name' => 'Bob']);

            expect($teamA->members)->toHaveCount(1)
                ->and($teamA->members->first()->name)->toBe('Alice');
        });

        it('has a hasMany relationship to Task', function (): void {
            $team = Team::create(['name' => 'Dev Team']);

            expect($team->tasks())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tasks', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            Task::create(['title' => 'Task 1', 'team_id' => $team->id]);
            Task::create(['title' => 'Task 2', 'team_id' => $team->id]);

            expect($team->tasks)->toHaveCount(2);
        });

        it('has a hasMany relationship to Note', function (): void {
            $team = Team::create(['name' => 'Dev Team']);

            expect($team->notes())->toBeInstanceOf(HasMany::class);
        });

        it('returns related notes', function (): void {
            $team = Team::create(['name' => 'Dev Team']);
            Note::create(['title' => 'Note 1', 'content' => 'Content', 'team_id' => $team->id]);

            expect($team->notes)->toHaveCount(1);
        });
    });
});
