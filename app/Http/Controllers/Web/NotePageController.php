<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteTag;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles the notes page rendering.
 *
 * Supports search, tag, team and member filtering, with pinned notes always appearing first.
 */
class NotePageController extends Controller
{
    /**
     * Display notes with optional search, tag, team and member filters.
     *
     * Returns only the notes-list partial for AJAX requests (used by filterManager).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $search = (string) $request->get('search', '');
        $filterTag = (string) $request->get('tag', '');
        $teamId = $request->get('team_id');
        $teamMemberId = $request->get('team_member_id');

        $notes = Note::query()
            ->with(['tags', 'teamMember', 'team'])
            ->when($search !== '', fn ($q) => $q->search($search))
            ->when(
                $filterTag !== '',
                fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tag', $filterTag))
            )
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when($teamMemberId, fn ($q) => $q->where('team_member_id', $teamMemberId))
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        $allTags = NoteTag::query()
            ->select('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        if ($request->ajax()) {
            return view('partials.notes-list', ['notes' => $notes]);
        }

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.notes.index', [
            'title' => 'Notes',
            'notes' => $notes,
            'allTags' => $allTags,
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
        ]);
    }

    /**
     * Display a single note detail page with auto-save fields.
     *
     * @param Note $note
     * @return View
     */
    public function show(Note $note): View
    {
        $note->load(['tags', 'teamMember.team', 'team']);

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.notes.show', [
            'title' => $note->title,
            'note' => $note,
            'breadcrumbs' => (new BreadcrumbBuilder())->forNote($note)->build(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => (string) $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => (string) $m->id, 'label' => $m->name, 'team_id' => (string) $m->team_id])->all(),
        ]);
    }

    /**
     * Store a new note from the create modal.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'          => ['nullable', 'string', 'max:255'],
            'content'        => ['nullable', 'string'],
            'team_id'        => ['nullable', 'integer', 'exists:teams,id'],
            'team_member_id' => ['nullable', 'integer', 'exists:team_members,id'],
        ]);

        Note::create([
            'user_id'        => $request->user()->id,
            'title'          => $validated['title'] ?: 'Untitled',
            'content'        => $validated['content'] ?? '',
            'team_id'        => $validated['team_id'] ?? null,
            'team_member_id' => $validated['team_member_id'] ?? null,
        ]);

        return redirect()->route('notes.index');
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
            'title'          => ['sometimes', 'string', 'max:255'],
            'content'        => ['sometimes', 'string'],
            'team_id'        => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'team_member_id' => ['sometimes', 'nullable', 'integer', 'exists:team_members,id'],
            'is_pinned'      => ['sometimes', 'boolean'],
        ]);

        $note->update($validated);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
        }

        return redirect()->back();
    }

    /**
     * Delete a note.
     *
     * @param Request $request
     * @param Note $note
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, Note $note): JsonResponse|RedirectResponse
    {
        $note->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notes.index');
    }
}
