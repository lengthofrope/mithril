<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Events\BilaScheduled;
use App\Http\Controllers\Controller;
use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles bila (1-on-1 meeting) page rendering.
 *
 * Provides an overview of upcoming and past bilas, and a detail page
 * with prep items and notes for a single bila session.
 */
class BilaPageController extends Controller
{
    /**
     * Display all bilas split into upcoming and past groups.
     *
     * Returns only the bilas-list partial for AJAX requests (used by filterManager).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $teamMemberId = $request->get('team_member_id');
        $teamId = $request->get('team_id');

        $baseQuery = fn () => Bila::query()
            ->when($teamMemberId, fn ($q) => $q->where('team_member_id', $teamMemberId))
            ->when($teamId, fn ($q) => $q->whereHas('teamMember', fn ($sub) => $sub->where('team_id', $teamId)))
            ->with(['teamMember', 'prepItems']);

        $upcomingBilas = $baseQuery()
            ->where('is_done', false)
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->get()
            ->each(fn (Bila $bila) => $bila->setRelation('member', $bila->teamMember));

        $pastBilas = $baseQuery()
            ->where(fn ($q) => $q
                ->where('is_done', true)
                ->orWhereDate('scheduled_date', '<', now()->toDateString())
            )
            ->orderByDesc('scheduled_date')
            ->get()
            ->each(fn (Bila $bila) => $bila->setRelation('member', $bila->teamMember));

        if ($request->ajax()) {
            return view('partials.bilas-list', [
                'upcomingBilas' => $upcomingBilas,
                'pastBilas' => $pastBilas,
            ]);
        }

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.bilas.index', [
            'title' => "Bila's",
            'upcomingBilas' => $upcomingBilas,
            'pastBilas' => $pastBilas,
            'selectedTeamMemberId' => $teamMemberId,
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
        ]);
    }

    /**
     * Store a new bila and fire the scheduling event.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'scheduled_date' => ['required', 'date'],
        ]);

        $bila = Bila::create([
            'user_id'        => $request->user()->id,
            'team_member_id' => $validated['team_member_id'],
            'scheduled_date' => $validated['scheduled_date'],
        ]);

        BilaScheduled::dispatch($bila);

        return redirect()->route('bilas.index');
    }

    /**
     * Display the detail page for a single bila with its prep items and notes.
     *
     * @param Bila $bila
     * @return View
     */
    public function show(Bila $bila): View
    {
        $bila->load(['teamMember.team', 'prepItems']);
        $bila->setRelation('member', $bila->teamMember);

        $previousBila = Bila::query()
            ->where('team_member_id', $bila->team_member_id)
            ->whereDate('scheduled_date', '<', $bila->scheduled_date->toDateString())
            ->orderByDesc('scheduled_date')
            ->first();

        $nextBila = Bila::query()
            ->where('team_member_id', $bila->team_member_id)
            ->whereDate('scheduled_date', '>', $bila->scheduled_date->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        return view('pages.bilas.show', [
            'title' => 'Bila — ' . $bila->teamMember->name,
            'bila' => $bila,
            'breadcrumbs' => (new BreadcrumbBuilder())->forBila($bila)->build(),
            'previousBila' => $previousBila,
            'nextBila' => $nextBila,
        ]);
    }

    /**
     * Update editable fields on an existing bila record.
     *
     * @param Request $request
     * @param Bila $bila
     * @return JsonResponse
     */
    public function update(Request $request, Bila $bila): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_date' => ['sometimes', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ]);

        $bila->update($validated);

        return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Mark a bila as done.
     *
     * @param Request $request
     * @param Bila $bila
     * @return JsonResponse|RedirectResponse
     */
    public function markDone(Request $request, Bila $bila): JsonResponse|RedirectResponse
    {
        $bila->update(['is_done' => true]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Revert a bila's done status back to not done.
     *
     * @param Request $request
     * @param Bila $bila
     * @return JsonResponse|RedirectResponse
     */
    public function undoDone(Request $request, Bila $bila): JsonResponse|RedirectResponse
    {
        $bila->update(['is_done' => false]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Delete a bila and redirect to the index.
     *
     * @param Request $request
     * @param Bila $bila
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, Bila $bila): JsonResponse|RedirectResponse
    {
        $bila->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('bilas.index');
    }

    /**
     * Store a new prep item for a bila.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storePrepItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'bila_id'        => ['nullable', 'integer', 'exists:bilas,id'],
            'content'        => ['required', 'string', 'max:1000'],
        ]);

        BilaPrepItem::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Update an existing bila prep item.
     *
     * @param Request $request
     * @param BilaPrepItem $bilaPrepItem
     * @return JsonResponse
     */
    public function updatePrepItem(Request $request, BilaPrepItem $bilaPrepItem): JsonResponse
    {
        $validated = $request->validate([
            'content'      => ['sometimes', 'string', 'max:1000'],
            'is_discussed' => ['sometimes', 'boolean'],
            'bila_id'      => ['sometimes', 'nullable', 'integer', 'exists:bilas,id'],
        ]);

        $bilaPrepItem->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a bila prep item.
     *
     * @param BilaPrepItem $bilaPrepItem
     * @return JsonResponse
     */
    public function destroyPrepItem(BilaPrepItem $bilaPrepItem): JsonResponse
    {
        $bilaPrepItem->delete();

        return response()->json(['success' => true]);
    }
}
