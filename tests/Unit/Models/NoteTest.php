<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\NoteTag;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Traits\Filterable;
use App\Models\Traits\Searchable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('Note model', function (): void {
    describe('traits', function (): void {
        it('uses the Filterable trait', function (): void {
            expect(in_array(Filterable::class, class_uses_recursive(Note::class)))->toBeTrue();
        });

        it('uses the Searchable trait', function (): void {
            expect(in_array(Searchable::class, class_uses_recursive(Note::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $note = Note::create([
                'title' => 'Meeting notes',
                'content' => '## Key points',
                'is_pinned' => true,
                'user_id' => $user->id,
            ]);

            expect($note->title)->toBe('Meeting notes')
                ->and($note->is_pinned)->toBeTrue();
        });
    });

    describe('casts', function (): void {
        it('casts is_pinned to boolean', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Pinned note', 'content' => '', 'is_pinned' => true, 'user_id' => $user->id]);

            expect($note->fresh()->is_pinned)->toBeTrue();
        });

        it('defaults is_pinned to false', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Unpinned note', 'content' => '', 'user_id' => $user->id]);

            expect($note->fresh()->is_pinned)->toBeFalse();
        });

        it('casts date to a date instance', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Dated note', 'content' => '', 'date' => '2026-03-15', 'user_id' => $user->id]);

            expect($note->fresh()->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
                ->and($note->fresh()->date->toDateString())->toBe('2026-03-15');
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Team', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $note = Note::create(['title' => 'Note', 'content' => '', 'team_id' => $team->id, 'user_id' => $user->id]);

            expect($note->team())->toBeInstanceOf(BelongsTo::class)
                ->and($note->team->id)->toBe($team->id);
        });

        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $note = Note::create(['title' => 'Note', 'content' => '', 'team_member_id' => $member->id, 'user_id' => $user->id]);

            expect($note->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($note->teamMember->id)->toBe($member->id);
        });

        it('has a hasMany relationship to NoteTag', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Note', 'content' => '', 'user_id' => $user->id]);

            expect($note->tags())->toBeInstanceOf(HasMany::class);
        });

        it('returns related tags', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Note', 'content' => '', 'user_id' => $user->id]);
            NoteTag::create(['note_id' => $note->id, 'tag' => 'laravel', 'user_id' => $user->id]);
            NoteTag::create(['note_id' => $note->id, 'tag' => 'php', 'user_id' => $user->id]);

            expect($note->tags)->toHaveCount(2);
        });

        it('does not include tags from other notes', function (): void {
            $user = User::factory()->create();
            $noteA = Note::create(['title' => 'Note A', 'content' => '', 'user_id' => $user->id]);
            $noteB = Note::create(['title' => 'Note B', 'content' => '', 'user_id' => $user->id]);
            NoteTag::create(['note_id' => $noteA->id, 'tag' => 'alpha', 'user_id' => $user->id]);
            NoteTag::create(['note_id' => $noteB->id, 'tag' => 'beta', 'user_id' => $user->id]);

            expect($noteA->tags)->toHaveCount(1)
                ->and($noteA->tags->first()->tag)->toBe('alpha');
        });

        it('allows null team_id and team_member_id', function (): void {
            $user = User::factory()->create();
            $note = Note::create(['title' => 'Standalone note', 'content' => '', 'user_id' => $user->id]);

            expect($note->team)->toBeNull()
                ->and($note->teamMember)->toBeNull();
        });
    });
});
