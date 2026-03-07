<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', TaskStatus::Done)])
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

        $memberNotes = Note::query()
            ->where('team_member_id', $teamMember->id)
            ->with('tags')
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        return view('pages.teams.member', [
            'title' => $teamMember->name,
            'member' => $teamMember,
            'memberTasks' => $teamMember->tasks,
            'memberFollowUps' => $teamMember->followUps,
            'memberBilas' => $teamMember->bilas,
            'memberAgreements' => $teamMember->agreements,
            'memberNotes' => $memberNotes,
        ]);
    }

    /**
     * Update editable profile fields on a team member record.
     *
     * @param Request $request
     * @param TeamMember $teamMember
     * @return JsonResponse
     */
    public function updateMember(Request $request, TeamMember $teamMember): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'role'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'              => ['sometimes', 'nullable', 'email', 'max:255'],
            'notes'              => ['sometimes', 'nullable', 'string'],
            'status'             => ['sometimes', 'string'],
            'bila_interval_days' => ['sometimes', 'integer', 'min:1'],
            'next_bila_date'     => ['sometimes', 'nullable', 'date'],
        ]);

        $teamMember->update($validated);

        return response()->json(['success' => true]);
    }
}
