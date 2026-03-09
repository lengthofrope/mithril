<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\BreadcrumbBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->builder = new BreadcrumbBuilder();
});

test('home crumb is always first', function () {
    $crumbs = $this->builder->forPage('Tasks', route('tasks.index'))->build();

    expect($crumbs[0])->toBe(['label' => 'Home', 'url' => '/']);
});

test('simple index page produces two crumbs', function () {
    $crumbs = $this->builder->forPage('Tasks', route('tasks.index'))->build();

    expect($crumbs)->toHaveCount(2)
        ->and($crumbs[1])->toBe(['label' => 'Tasks', 'url' => null]);
});

test('task show without team produces three crumbs', function () {
    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $crumbs = $this->builder->forTask($task)->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[0])->toBe(['label' => 'Home', 'url' => '/'])
        ->and($crumbs[1])->toBe(['label' => 'Tasks', 'url' => route('tasks.index')])
        ->and($crumbs[2])->toBe(['label' => $task->title, 'url' => null]);
});

test('task show with team and member produces full hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);
    $task->load(['team', 'teamMember']);

    $crumbs = $this->builder->forTask($task)->build();

    expect($crumbs)->toHaveCount(5)
        ->and($crumbs[0])->toBe(['label' => 'Home', 'url' => '/'])
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $member->name, 'url' => route('teams.member', $member)])
        ->and($crumbs[4])->toBe(['label' => $task->title, 'url' => null]);
});

test('task show with team only produces team hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
        'team_member_id' => null,
    ]);
    $task->load(['team', 'teamMember']);

    $crumbs = $this->builder->forTask($task)->build();

    expect($crumbs)->toHaveCount(4)
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $task->title, 'url' => null]);
});

test('team show produces three crumbs', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);

    $crumbs = $this->builder->forTeam($team)->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => null]);
});

test('team member produces four crumbs', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id]);
    $member->load('team');

    $crumbs = $this->builder->forTeamMember($member)->build();

    expect($crumbs)->toHaveCount(4)
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $member->name, 'url' => null]);
});

test('bila show produces crumbs through member hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id]);
    $bila = Bila::factory()->create(['team_member_id' => $member->id, 'user_id' => $this->user->id]);
    $bila->load(['teamMember.team']);

    $crumbs = $this->builder->forBila($bila)->build();

    expect($crumbs)->toHaveCount(5)
        ->and($crumbs[0])->toBe(['label' => 'Home', 'url' => '/'])
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $member->name, 'url' => route('teams.member', $member)])
        ->and($crumbs[4])->toBe(['label' => 'Bila — ' . $member->name, 'url' => null]);
});

test('note show without associations produces three crumbs', function () {
    $note = Note::factory()->create(['user_id' => $this->user->id]);

    $crumbs = $this->builder->forNote($note)->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[1])->toBe(['label' => 'Notes', 'url' => route('notes.index')])
        ->and($crumbs[2])->toBe(['label' => $note->title, 'url' => null]);
});

test('note show with team member produces full hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id]);
    $note = Note::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);
    $note->load(['team', 'teamMember.team']);

    $crumbs = $this->builder->forNote($note)->build();

    expect($crumbs)->toHaveCount(5)
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $member->name, 'url' => route('teams.member', $member)])
        ->and($crumbs[4])->toBe(['label' => $note->title, 'url' => null]);
});

test('note show with team only produces team hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $note = Note::factory()->create([
        'user_id' => $this->user->id,
        'team_id' => $team->id,
        'team_member_id' => null,
    ]);
    $note->load(['team', 'teamMember']);

    $crumbs = $this->builder->forNote($note)->build();

    expect($crumbs)->toHaveCount(4)
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $note->title, 'url' => null]);
});

test('settings tasks produces nested settings crumbs', function () {
    $crumbs = $this->builder
        ->forPage('Settings', route('settings.index'))
        ->addCrumb('Task Settings')
        ->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[1])->toBe(['label' => 'Settings', 'url' => route('settings.index')])
        ->and($crumbs[2])->toBe(['label' => 'Task Settings', 'url' => null]);
});

test('kanban produces tasks sub-crumb', function () {
    $crumbs = $this->builder
        ->forPage('Tasks', route('tasks.index'))
        ->addCrumb('Kanban')
        ->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[1])->toBe(['label' => 'Tasks', 'url' => route('tasks.index')])
        ->and($crumbs[2])->toBe(['label' => 'Kanban', 'url' => null]);
});

test('follow-up show without member produces three crumbs', function () {
    $followUp = FollowUp::factory()->create([
        'user_id' => $this->user->id,
        'team_member_id' => null,
    ]);

    $crumbs = $this->builder->forFollowUp($followUp)->build();

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[0])->toBe(['label' => 'Home', 'url' => '/'])
        ->and($crumbs[1])->toBe(['label' => 'Follow-ups', 'url' => route('follow-ups.index')])
        ->and($crumbs[2])->toBe(['label' => $followUp->description, 'url' => null]);
});

test('follow-up show with team member produces full hierarchy', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id]);
    $followUp = FollowUp::factory()->create([
        'user_id' => $this->user->id,
        'team_member_id' => $member->id,
    ]);
    $followUp->load(['teamMember.team']);

    $crumbs = $this->builder->forFollowUp($followUp)->build();

    expect($crumbs)->toHaveCount(5)
        ->and($crumbs[0])->toBe(['label' => 'Home', 'url' => '/'])
        ->and($crumbs[1])->toBe(['label' => 'Teams', 'url' => route('teams.index')])
        ->and($crumbs[2])->toBe(['label' => $team->name, 'url' => route('teams.show', $team)])
        ->and($crumbs[3])->toBe(['label' => $member->name, 'url' => route('teams.member', $member)])
        ->and($crumbs[4])->toBe(['label' => $followUp->description, 'url' => null]);
});

test('last crumb always has null url', function () {
    $team = Team::factory()->create(['user_id' => $this->user->id]);

    $crumbs = $this->builder->forTeam($team)->build();

    $lastCrumb = end($crumbs);
    expect($lastCrumb['url'])->toBeNull();
});
