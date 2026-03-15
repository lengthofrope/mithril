<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Note;
use App\Models\NoteTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles syncing tags for a specific note.
 */
class NoteTagController extends Controller
{
    use ApiResponse;

    /**
     * Replace all tags on a note with the given set.
     *
     * @param Request $request
     * @param Note $note
     * @return JsonResponse
     */
    public function sync(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate([
            'tags' => ['present', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $tags = collect($validated['tags'])
            ->map(fn (string $tag): string => mb_strtolower(trim($tag)))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values();

        $note->tags()->delete();

        $tags->each(fn (string $tag) => NoteTag::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'tag' => $tag,
        ]));

        return $this->successResponse(
            $note->fresh()->tags->pluck('tag')->values()->all(),
            '',
            200,
            true,
        );
    }
}
