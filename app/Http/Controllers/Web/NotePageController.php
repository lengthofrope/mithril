<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteTag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the notes page rendering.
 *
 * Supports search and tag filtering, with pinned notes always appearing first.
 */
class NotePageController extends Controller
{
    /**
     * Display notes with optional search and tag filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $searchTerm = (string) $request->get('q', '');
        $filterTag = (string) $request->get('tag', '');

        $notes = Note::query()
            ->with(['tags', 'teamMember', 'team'])
            ->when($searchTerm !== '', fn ($q) => $q->search($searchTerm))
            ->when(
                $filterTag !== '',
                fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tag', $filterTag))
            )
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        $availableTags = NoteTag::query()
            ->select('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        return view('pages.notes.index', [
            'title' => 'Notes',
            'notes' => $notes,
            'availableTags' => $availableTags,
            'searchTerm' => $searchTerm,
            'selectedTag' => $filterTag,
        ]);
    }
}
