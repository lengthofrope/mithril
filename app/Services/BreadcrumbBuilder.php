<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bila;
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
     * Build breadcrumbs for a bila detail page.
     *
     * Routes through the team member's hierarchy.
     *
     * @param Bila $bila
     * @return self
     */
    public function forBila(Bila $bila): self
    {
        $this->crumbs = [['label' => 'Home', 'url' => '/']];

        if ($bila->teamMember) {
            $this->addTeamMemberChain($bila->teamMember, linked: true);
            $this->crumbs[] = ['label' => 'Bila — ' . $bila->teamMember->name, 'url' => null];
        } else {
            $this->crumbs[] = ['label' => "Bila's", 'url' => route('bilas.index')];
            $this->crumbs[] = ['label' => 'Bila', 'url' => null];
        }

        return $this;
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
