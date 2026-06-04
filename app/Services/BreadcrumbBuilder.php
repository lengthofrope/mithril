<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingType;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;

/**
 * Builds contextual, hierarchical breadcrumb arrays based on entity relationships.
 *
 * Each crumb is an associative array with 'label' and 'url' keys.
 * The last crumb always has a null url (current page).
 */
class BreadcrumbBuilder
{
    /** @var array<int, array{label: string, url: string|null}> */
    private array $crumbs = [];

    /**
     * Start a breadcrumb trail for a simple page (e.g. index pages).
     *
     * @param string $label
     * @param string|null $url
     * @return self
     */
    public function forPage(string $label, ?string $url = null): self
    {
        $this->crumbs = [
            ['label' => 'Home', 'url' => '/'],
            ['label' => $label, 'url' => $url],
        ];

        return $this;
    }

    /**
     * Add an additional crumb to the trail.
     *
     * Converts the previous last crumb into a linked crumb and appends the new one.
     *
     * @param string $label
     * @param string|null $url
     * @return self
     */
    public function addCrumb(string $label, ?string $url = null): self
    {
        $this->crumbs[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * Build breadcrumbs for a task detail page.
     *
     * Routes through team/member hierarchy when the task is assigned.
     *
     * @param Task $task
     * @return self
     */
    public function forTask(Task $task): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];

        if ($task->team_member_id && $task->teamMember) {
            $this->addTeamMemberChain($task->teamMember, linked: true);
        } elseif ($task->team_id && $task->team) {
            $this->addTeamChain($task->team);
        } else {
            $this->crumbs[] = ['label' => 'Tasks', 'url' => route('tasks.index')];
        }

        $this->crumbs[] = ['label' => $task->title, 'url' => null];

        return $this;
    }

    /**
     * Build breadcrumbs for a follow-up detail page.
     *
     * Routes through team/member hierarchy when the follow-up is linked to a member.
     *
     * @param FollowUp $followUp
     * @return self
     */
    public function forFollowUp(FollowUp $followUp): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];

        if ($followUp->team_member_id && $followUp->teamMember) {
            $this->addTeamMemberChain($followUp->teamMember, linked: true);
        } else {
            $this->crumbs[] = ['label' => 'Follow-ups', 'url' => route('follow-ups.index')];
        }

        $this->crumbs[] = ['label' => $followUp->title, 'url' => null];

        return $this;
    }

    /**
     * Build breadcrumbs for a team detail page.
     *
     * @param Team $team
     * @return self
     */
    public function forTeam(Team $team): self
    {
        $this->crumbs = [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Teams', 'url' => route('teams.index')],
            ['label' => $team->name, 'url' => null],
        ];

        return $this;
    }

    /**
     * Build breadcrumbs for a team member profile page.
     *
     * @param TeamMember $member
     * @return self
     */
    public function forTeamMember(TeamMember $member): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];
        $this->addTeamMemberChain($member);

        return $this;
    }

    /**
     * Build breadcrumbs for a meeting detail page.
     *
     * Routes through the team or attendee hierarchy.
     *
     * @param Meeting $meeting
     * @return self
     */
    public function forMeeting(Meeting $meeting): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];

        match ($meeting->type) {
            MeetingType::OneOnOne => $this->buildOneOnOneMeetingCrumbs($meeting),
            MeetingType::Team => $this->buildTeamMeetingCrumbs($meeting),
            MeetingType::Other => $this->crumbs[] = ['label' => 'Meetings', 'url' => route('meetings.index')],
        };

        $this->crumbs[] = ['label' => $meeting->title, 'url' => null];

        return $this;
    }

    /**
     * Build breadcrumb chain for a one-on-one meeting via the attendee's team hierarchy.
     *
     * @param Meeting $meeting
     * @return void
     */
    private function buildOneOnOneMeetingCrumbs(Meeting $meeting): void
    {
        $attendee = $meeting->attendees->first();

        if ($attendee) {
            $this->addTeamMemberChain($attendee, linked: true);
        } else {
            $this->crumbs[] = ['label' => 'Meetings', 'url' => route('meetings.index')];
        }
    }

    /**
     * Build breadcrumb chain for a team meeting based on the number of distinct teams.
     *
     * Single team: Home > Teams > Team X > title.
     * Multiple teams: Home > Teams > title.
     *
     * @param Meeting $meeting
     * @return void
     */
    private function buildTeamMeetingCrumbs(Meeting $meeting): void
    {
        $uniqueTeams = $meeting->attendees
            ->pluck('team')
            ->filter()
            ->unique('id');

        if ($uniqueTeams->count() === 1) {
            $this->addTeamChain($uniqueTeams->first());
        } elseif ($uniqueTeams->isEmpty() && $meeting->team) {
            $this->addTeamChain($meeting->team);
        } else {
            $this->crumbs[] = ['label' => 'Teams', 'url' => route('teams.index')];
        }
    }

    /**
     * Build breadcrumbs for a note detail page.
     *
     * Routes through team/member hierarchy when associated.
     *
     * @param Note $note
     * @return self
     */
    public function forNote(Note $note): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];

        if ($note->team_member_id && $note->teamMember) {
            $this->addTeamMemberChain($note->teamMember, linked: true);
        } elseif ($note->team_id && $note->team) {
            $this->addTeamChain($note->team);
        } else {
            $this->crumbs[] = ['label' => 'Notes', 'url' => route('notes.index')];
        }

        $this->crumbs[] = ['label' => $note->title, 'url' => null];

        return $this;
    }

    /**
     * Return the built breadcrumb array.
     *
     * Ensures the last crumb has a null url.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public function build(): array
    {
        if (count($this->crumbs) > 0) {
            $this->crumbs[count($this->crumbs) - 1]['url'] = null;
        }

        return $this->crumbs;
    }

    /**
     * Append team > member chain to the breadcrumb trail.
     *
     * @param TeamMember $member
     * @param bool $linked Whether to add the member as a link (true) or terminal crumb (false).
     * @return void
     */
    private function addTeamMemberChain(TeamMember $member, bool $linked = false): void
    {
        if ($member->team) {
            $this->crumbs[] = ['label' => 'Teams', 'url' => route('teams.index')];
            $this->crumbs[] = ['label' => $member->team->name, 'url' => route('teams.show', $member->team)];
        }

        $this->crumbs[] = [
            'label' => $member->name,
            'url' => $linked ? route('teams.member', $member) : null,
        ];
    }

    /**
     * Append team chain to the breadcrumb trail.
     *
     * @param Team $team
     * @return void
     */
    private function addTeamChain(Team $team): void
    {
        $this->crumbs[] = ['label' => 'Teams', 'url' => route('teams.index')];
        $this->crumbs[] = ['label' => $team->name, 'url' => route('teams.show', $team)];
    }
}
