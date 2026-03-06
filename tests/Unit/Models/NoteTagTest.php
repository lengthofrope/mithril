<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\NoteTag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
describe('NoteTag model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of note_id and tag', function (): void {
            $note = Note::create(['title' => 'A note', 'content' => '']);
            $tag = NoteTag::create(['note_id' => $note->id, 'tag' => 'php']);

            expect($tag->tag)->toBe('php')
                ->and($tag->note_id)->toBe($note->id);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Note', function (): void {
            $note = Note::create(['title' => 'A note', 'content' => '']);
            $tag = NoteTag::create(['note_id' => $note->id, 'tag' => 'php']);

            expect($tag->note())->toBeInstanceOf(BelongsTo::class)
                ->and($tag->note->id)->toBe($note->id);
        });

        it('resolves the parent note with correct title', function (): void {
            $note = Note::create(['title' => 'My Important Note', 'content' => '']);
            $tag = NoteTag::create(['note_id' => $note->id, 'tag' => 'important']);

            expect($tag->note->title)->toBe('My Important Note');
        });
    });
});
