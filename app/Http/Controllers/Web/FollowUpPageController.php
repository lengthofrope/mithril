<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use App\Services\MetadataTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles the follow-ups page rendering.
 *
 * Groups follow-ups by timeline: overdue, today, this week, and upcoming.
 */
class FollowUpPageController extends Controller
{
    /**
     * Display all follow-ups grouped by their timeline category.
     *
     * Returns only the follow-ups-list partial for AJAX requests (used by filterManager).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $teamId = $request->get('team_id');
        $teamMemberId = $request->get('team_member_id');
        $search = $request->get('search');

        $memberIdsForTeam = $teamId
            ? TeamMember::where('team_id', $teamId)->pluck('id')
            : null;

        $baseQuery = fn () => FollowUp::query()
            ->when($memberIdsForTeam, fn ($q) => $q->whereIn('team_member_id', $memberIdsForTeam))
            ->when($teamMemberId, fn ($q) => $q->where('team_member_id', $teamMemberId))
            ->when($search, fn ($q) => $q->search($search))
            ->with(['teamMember', 'task']);

        $overdue = $baseQuery()
            ->overdue()
            ->orderBy('follow_up_date')
            ->get();

        $today = $baseQuery()
            ->dueToday()
            ->orderBy('follow_up_date')
            ->get();

        $thisWeek = $baseQuery()
            ->dueThisWeek()
            ->orderBy('follow_up_date')
            ->get();

        $upcoming = $baseQuery()
            ->upcoming()
            ->orderBy('follow_up_date')
            ->get();

        $sections = [
            'overdue' => $overdue,
            'today' => $today,
            'this_week' => $thisWeek,
            'later' => $upcoming,
        ];

        if ($request->wantsJson()) {
            return view('partials.follow-ups-list', [
                'sections' => $sections,
            ]);
        }

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.follow-ups.index', [
            'title' => 'Follow-ups',
            'sections' => $sections,
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
            'selectedTeamMemberId' => $teamMemberId,
        ]);
    }

    /**
     * Display the follow-up detail/edit page.
     *
     * @param FollowUp $followUp
     * @return View
     */
    public function show(FollowUp $followUp): View
    {
        $followUp->load(['teamMember.team', 'task']);

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.follow-ups.show', [
            'title' => $followUp->description,
            'followUp' => $followUp,
            'breadcrumbs' => (new BreadcrumbBuilder())->forFollowUp($followUp)->build(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => (string) $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => (string) $m->id, 'label' => $m->name, 'team_id' => (string) $m->team_id])->all(),
            'statusOptions' => array_map(
                fn (FollowUpStatus $s) => ['value' => $s->value, 'label' => ucfirst($s->value)],
                FollowUpStatus::cases(),
            ),
        ]);
    }

    /**
     * Store a new follow-up from the create modal.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description'    => ['required', 'string'],
            'team_member_id' => ['nullable', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'waiting_on'     => ['nullable', 'string', 'max:255'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        $followUp = FollowUp::create([
            'user_id'        => $request->user()->id,
            'description'    => $validated['description'],
            'team_member_id' => $validated['team_member_id'] ?? null,
            'waiting_on'     => $validated['waiting_on'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? now()->toDateString(),
            'status'         => FollowUpStatus::Open->value,
        ]);

        return redirect()->route('follow-ups.show', $followUp);
    }

    /**
     * Delete a follow-up.
     *
     * @param Request $request
     * @param FollowUp $followUp
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, FollowUp $followUp): JsonResponse|RedirectResponse
    {
        $followUp->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('follow-ups.index');
    }

    /**
     * Mark a follow-up as done.
     *
     * Returns JSON for AJAX requests, redirects back otherwise.
     *
     * @param Request $request
     * @param FollowUp $followUp
     * @return JsonResponse|RedirectResponse
     */
    public function markDone(Request $request, FollowUp $followUp): JsonResponse|RedirectResponse
    {
        $followUp->update(['status' => FollowUpStatus::Done]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Snooze a follow-up by a number of days.
     *
     * Returns JSON for AJAX requests, redirects back otherwise.
     *
     * @param Request $request
     * @param FollowUp $followUp
     * @return JsonResponse|RedirectResponse
     */
    public function snooze(Request $request, FollowUp $followUp): JsonResponse|RedirectResponse
    {
        $request->validate(['days' => ['required', 'integer', 'min:1']]);

        $followUp->update([
            'follow_up_date' => now()->addDays((int) $request->input('days'))->toDateString(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Convert a follow-up into a new task.
     *
     * Returns JSON for AJAX requests, redirects back otherwise.
     *
     * @param Request $request
     * @param FollowUp $followUp
     * @return JsonResponse|RedirectResponse
     */
    public function convertToTask(Request $request, FollowUp $followUp, MetadataTransferService $metadataTransfer): JsonResponse|RedirectResponse
    {
        $task = Task::create([
            'user_id' => $request->user()->id,
            'title' => $followUp->description,
            'team_member_id' => $followUp->team_member_id,
            'deadline' => $followUp->follow_up_date,
        ]);

        $metadataTransfer->transfer($followUp, $task);
        $followUp->update(['status' => FollowUpStatus::Done]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['task_url' => route('tasks.show', $task)],
            ]);
        }

        return redirect()->route('tasks.show', $task);
    }
}
