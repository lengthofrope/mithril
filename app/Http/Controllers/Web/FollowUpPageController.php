<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;
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

        return view('pages.follow-ups.index', [
            'title' => 'Follow-ups',
            'overdue' => $overdue,
            'today' => $today,
            'thisWeek' => $thisWeek,
            'upcoming' => $upcoming,
            'teamMembers' => TeamMember::orderBySortOrder()->get(),
            'selectedTeamMemberId' => $teamMemberId,
        ]);
    }
}
