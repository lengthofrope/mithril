<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Events\MeetingScheduled;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles meeting page rendering and interaction.
 *
 * Provides an overview of upcoming and past meetings, and a detail page
 * with attendees, prep items, notes, and activity feed.
 */
class MeetingPageController extends Controller
{
    /**
     * Display all meetings split into upcoming and past groups.
     *
     * Returns only the meetings-list partial for AJAX requests (used by filterManager).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $teamMemberId = $request->get('team_member_id');
        $teamId = $request->get('team_id');
        $type = $request->get('type');
        $status = $request->get('status');

        $baseQuery = fn () => Meeting::query()
            ->when($teamMemberId, fn ($q) => $q->whereHas('attendees', fn ($sub) => $sub->where('team_member_id', $teamMemberId)))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['attendees', 'prepItems']);

        $upcomingMeetings = $baseQuery()
            ->where('is_done', false)
            ->whereDate('scheduled_at', '>=', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        $pastMeetings = $baseQuery()
            ->where(fn ($q) => $q
                ->where('is_done', true)
                ->orWhereDate('scheduled_at', '<', now()->toDateString())
            )
            ->orderByDesc('scheduled_at')
            ->get();

        if ($request->ajax()) {
            return view('partials.meetings-list', [
                'upcomingMeetings' => $upcomingMeetings,
                'pastMeetings' => $pastMeetings,
            ]);
        }

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        $typeOptions = array_map(
            fn (MeetingType $t) => ['value' => $t->value, 'label' => match ($t) {
                MeetingType::OneOnOne => '1-on-1',
                MeetingType::Team => 'Team',
                MeetingType::Other => 'Other',
            }],
            MeetingType::cases(),
        );

        $statusOptions = array_map(
            fn (MeetingStatus $s) => ['value' => $s->value, 'label' => match ($s) {
                MeetingStatus::Scheduled => 'Scheduled',
                MeetingStatus::InProgress => 'In progress',
                MeetingStatus::Completed => 'Completed',
                MeetingStatus::Cancelled => 'Cancelled',
            }],
            MeetingStatus::cases(),
        );

        return view('pages.meetings.index', [
            'title' => 'Meetings',
            'upcomingMeetings' => $upcomingMeetings,
            'pastMeetings' => $pastMeetings,
            'selectedTeamMemberId' => $teamMemberId,
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
            'typeOptions' => $typeOptions,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Store a new meeting and fire the scheduling event.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MeetingType::class)],
            'scheduled_at' => ['required', 'date'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('user_id', auth()->id())],
            'attendee_ids' => ['sometimes', 'array'],
            'attendee_ids.*' => ['integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
        ]);

        $meeting = Meeting::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'scheduled_at' => $validated['scheduled_at'],
            'team_id' => $validated['team_id'] ?? null,
        ]);

        if (!empty($validated['attendee_ids'])) {
            $meeting->attendees()->attach($validated['attendee_ids']);
        }

        MeetingScheduled::dispatch($meeting);

        return redirect()->route('meetings.show', $meeting);
    }

    /**
     * Display the detail page for a single meeting.
     *
     * @param Meeting $meeting
     * @return View
     */
    public function show(Meeting $meeting): View
    {
        $meeting->load(['attendees.team', 'prepItems.teamMember', 'recordings', 'team']);

        $previousMeeting = $this->findAdjacentMeeting($meeting, 'previous');
        $nextMeeting = $this->findAdjacentMeeting($meeting, 'next');

        $attendeeOptions = $meeting->attendees->map(fn (TeamMember $m) => [
            'value' => $m->id,
            'label' => $m->name,
        ])->all();

        return view('pages.meetings.show', [
            'title' => $meeting->title,
            'meeting' => $meeting,
            'breadcrumbs' => (new BreadcrumbBuilder())->forMeeting($meeting)->build(),
            'previousMeeting' => $previousMeeting,
            'nextMeeting' => $nextMeeting,
            'attendeeOptions' => $attendeeOptions,
        ]);
    }

    /**
     * Update editable fields on an existing meeting record.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'title' => ['sometimes', 'string', 'max:255'],
        ]);

        $meeting->update($validated);

        return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Mark a meeting as done.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse|RedirectResponse
     */
    public function markDone(Request $request, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $meeting->update(['is_done' => true, 'status' => 'completed']);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Revert a meeting's done status back to not done.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse|RedirectResponse
     */
    public function undoDone(Request $request, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $meeting->update(['is_done' => false, 'status' => 'scheduled']);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    /**
     * Transition a meeting to a new status.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse
     */
    public function transition(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(MeetingStatus::class)],
        ]);

        $newStatus = MeetingStatus::from($validated['status']);

        $updates = ['status' => $newStatus];

        if ($newStatus === MeetingStatus::InProgress && $meeting->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($newStatus === MeetingStatus::Completed) {
            $updates['ended_at'] = now();
            $updates['is_done'] = true;
        }

        if ($newStatus === MeetingStatus::Cancelled) {
            $updates['is_done'] = true;
        }

        $meeting->update($updates);

        return response()->json(['success' => true, 'status' => $newStatus->value]);
    }

    /**
     * Delete a meeting and redirect to the index.
     *
     * @param Request $request
     * @param Meeting $meeting
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, Meeting $meeting): JsonResponse|RedirectResponse
    {
        $meeting->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('meetings.index');
    }

    /**
     * Store a new prep item for a meeting.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storePrepItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'meeting_id' => ['required', 'integer', Rule::exists('meetings', 'id')->where('user_id', auth()->id())],
            'team_member_id' => ['nullable', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'content' => ['required', 'string', 'max:1000'],
            'type' => ['sometimes', Rule::enum(\App\Enums\PrepItemType::class)],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        MeetingPrepItem::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Update an existing meeting prep item.
     *
     * @param Request $request
     * @param MeetingPrepItem $meetingPrepItem
     * @return JsonResponse
     */
    public function updatePrepItem(Request $request, MeetingPrepItem $meetingPrepItem): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['sometimes', 'string', 'max:1000'],
            'is_discussed' => ['sometimes', 'boolean'],
            'type' => ['sometimes', Rule::enum(\App\Enums\PrepItemType::class)],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $meetingPrepItem->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a meeting prep item.
     *
     * @param MeetingPrepItem $meetingPrepItem
     * @return JsonResponse
     */
    public function destroyPrepItem(MeetingPrepItem $meetingPrepItem): JsonResponse
    {
        $meetingPrepItem->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Find the previous or next meeting relative to the given meeting.
     *
     * For one_on_one meetings, navigates by attendee. For team meetings, by team.
     *
     * @param Meeting $meeting
     * @param string $direction Either 'previous' or 'next'.
     * @return Meeting|null
     */
    private function findAdjacentMeeting(Meeting $meeting, string $direction): ?Meeting
    {
        $query = Meeting::query();

        if ($meeting->type === MeetingType::OneOnOne && $meeting->attendees->isNotEmpty()) {
            $attendeeId = $meeting->attendees->first()->id;
            $query->whereHas('attendees', fn ($q) => $q->where('team_member_id', $attendeeId));
        } elseif ($meeting->team_id) {
            $query->where('team_id', $meeting->team_id);
        } else {
            return null;
        }

        if ($direction === 'previous') {
            $query->where('scheduled_at', '<', $meeting->scheduled_at)
                ->orderByDesc('scheduled_at');
        } else {
            $query->where('scheduled_at', '>', $meeting->scheduled_at)
                ->orderBy('scheduled_at');
        }

        return $query->first();
    }
}
