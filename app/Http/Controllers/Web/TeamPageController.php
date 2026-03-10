<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            'breadcrumbs' => (new BreadcrumbBuilder())->forTeam($team)->build(),
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
            'breadcrumbs' => (new BreadcrumbBuilder())->forTeamMember($teamMember)->build(),
            'memberTasks' => $teamMember->tasks,
            'memberFollowUps' => $teamMember->followUps,
            'memberBilas' => $teamMember->bilas,
            'memberAgreements' => $teamMember->agreements,
            'memberNotes' => $memberNotes,
        ]);
    }

    /**
     * Store a newly created team.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        Team::create($validated);

        return redirect()->back();
    }

    /**
     * Update an existing team.
     *
     * @param Request $request
     * @param Team $team
     * @return RedirectResponse
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $team->update($validated);

        return redirect()->back();
    }

    /**
     * Delete a team and all its members.
     *
     * @param Team $team
     * @return RedirectResponse
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->members()->delete();
        $team->delete();

        return redirect()->route('teams.index');
    }

    /**
     * Store a new team member for the given team.
     *
     * @param Request $request
     * @param Team $team
     * @return RedirectResponse
     */
    public function storeMember(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $team->members()->create($validated);

        return redirect()->back();
    }

    /**
     * Delete a team member and redirect to their team page.
     *
     * @param TeamMember $teamMember
     * @return RedirectResponse
     */
    public function destroyMember(TeamMember $teamMember): RedirectResponse
    {
        $teamId = $teamMember->team_id;
        $teamMember->delete();

        return redirect()->route('teams.show', $teamId);
    }

    /**
     * Upload and store a new avatar for a team member.
     *
     * @param Request $request
     * @param TeamMember $teamMember
     * @return RedirectResponse
     */
    public function uploadMemberAvatar(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        if ($teamMember->avatar_path) {
            Storage::disk('public')->delete($teamMember->avatar_path);
        }

        $path = $request->file('avatar')->store('member-avatars', 'public');

        $teamMember->update(['avatar_path' => $path]);

        return redirect()->back();
    }

    /**
     * Remove the avatar for a team member.
     *
     * @param TeamMember $teamMember
     * @return RedirectResponse
     */
    public function deleteMemberAvatar(TeamMember $teamMember): RedirectResponse
    {
        if ($teamMember->avatar_path) {
            Storage::disk('public')->delete($teamMember->avatar_path);
            $teamMember->update(['avatar_path' => null]);
        }

        return redirect()->back();
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
            'status'             => ['sometimes', 'string', Rule::in(array_column(MemberStatus::cases(), 'value'))],
            'bila_interval_days' => ['sometimes', 'integer', 'min:1'],
            'next_bila_date'     => ['sometimes', 'nullable', 'date'],
            'microsoft_email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'status_source'      => ['sometimes', 'string', Rule::in(array_column(StatusSource::cases(), 'value'))],
        ]);

        if (isset($validated['status']) && $teamMember->status_source === StatusSource::Microsoft) {
            unset($validated['status']);
        }

        if (isset($validated['status_source']) && $validated['status_source'] === StatusSource::Microsoft->value) {
            $validated['status_synced_at'] = null;
        }

        $teamMember->update($validated);

        return response()->json(['success' => true]);
    }
}
