<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        $teamMemberId = $request->get('team_member_id');

        $baseQuery = fn () => FollowUp::query()
            ->when($teamMemberId, fn ($q) => $q->where('team_member_id', $teamMemberId))
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
            'thisWeek' => $thisWeek,
            'upcoming' => $upcoming,
        ];

        if ($request->ajax()) {
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

        if ($request->ajax()) {
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

        if ($request->ajax()) {
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
    public function convertToTask(Request $request, FollowUp $followUp): JsonResponse|RedirectResponse
    {
        Task::create([
            'user_id' => $request->user()->id,
            'title' => $followUp->description,
            'team_member_id' => $followUp->team_member_id,
        ]);

        $followUp->update(['status' => FollowUpStatus::Done]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
