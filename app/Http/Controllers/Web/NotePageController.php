<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteTag;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $allTags = NoteTag::query()
            ->select('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        return view('pages.notes.index', [
            'title' => 'Notes',
            'notes' => $notes,
            'allTags' => $allTags,
            'searchTerm' => $searchTerm,
            'selectedTag' => $filterTag,
        ]);
    }

    /**
     * Display a single note detail page.
     *
     * @param Note $note
     * @return View
     */
    public function show(Note $note): View
    {
        $note->load(['tags', 'teamMember.team', 'team']);

        return view('pages.notes.show', [
            'title' => $note->title,
            'note' => $note,
            'breadcrumbs' => (new BreadcrumbBuilder())->forNote($note)->build(),
        ]);
    }

    /**
     * Store a new note.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        Note::create([
            'user_id' => $request->user()->id,
            'title'   => $validated['title'] ?? 'Untitled',
            'content' => $validated['content'] ?? '',
        ]);

        return redirect()->back();
    }

    /**
     * Update a note (supports auto-save via AJAX).
     *
     * @param Request $request
     * @param Note $note
     * @return JsonResponse|RedirectResponse
     */
    public function update(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'title'   => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
        ]);

        $note->update($validated);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
        }

        return redirect()->back();
    }
}
