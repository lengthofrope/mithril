<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles team and team member page rendering.
 *
 * Provides team overview, team detail, and individual member profile pages.
 */
class TeamPageController extends Controller
{
    /**
     * Display all teams with their member counts.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $teams = Team::query()
            ->withCount('members')
            ->orderBySortOrder()
            ->get();

        return view('pages.teams.index', [
            'title' => 'Teams',
            'teams' => $teams,
        ]);
    }

    /**
     * Display a team's detail page including all its members.
     *
     * @param Team $team
     * @return View
     */
    public function show(Team $team): View
    {
        $team->load([
            'members' => fn ($q) => $q->orderBySortOrder(),
        ]);

        return view('pages.teams.show', [
            'title' => $team->name,
            'team' => $team,
        ]);
    }

    /**
     * Display a team member's profile page with all related data.
     *
     * @param TeamMember $teamMember
     * @return View
     */
    public function member(TeamMember $teamMember): View
    {
        $teamMember->load([
            'team',
            'tasks' => fn ($q) => $q->orderBySortOrder(),
            'followUps' => fn ($q) => $q->orderBy('follow_up_date'),
            'bilas' => fn ($q) => $q->orderBy('scheduled_date', 'desc'),
            'agreements' => fn ($q) => $q->orderBy('agreed_date', 'desc'),
        ]);

        return view('pages.teams.member', [
            'title' => $teamMember->name,
            'teamMember' => $teamMember,
        ]);
    }
}
