<?php

declare(strict_types=1);

use App\Models\Agreement;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/teams');

    $response->assertOk();
});

test('team index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/teams');

    $response->assertRedirect('/login');
});

test('team index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/teams');

    $response->assertViewIs('pages.teams.index');
});

test('team index passes teams to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Team::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/teams');

    $response->assertViewHas('teams');
    expect($response->viewData('teams'))->toHaveCount(3);
});

test('team index includes member counts on teams', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create();
    TeamMember::factory()->count(2)->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->get('/teams');

    $team = $response->viewData('teams')->first();
    expect($team->members_count)->toBe(2);
});

test('team show returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this->actingAs($user)->get("/teams/{$team->id}");

    $response->assertOk();
});

test('team show redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $team = Team::factory()->create();

    $response = $this->get("/teams/{$team->id}");

    $response->assertRedirect('/login');
});

test('team show renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this->actingAs($user)->get("/teams/{$team->id}");

    $response->assertViewIs('pages.teams.show');
});

test('team show passes team with members to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create();
    TeamMember::factory()->count(2)->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->get("/teams/{$team->id}");

    $response->assertViewHas('team');
    $viewTeam = $response->viewData('team');
    expect($viewTeam->id)->toBe($team->id);
    expect($viewTeam->members)->toHaveCount(2);
});

test('team show returns 404 for non-existent team', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/teams/9999');

    $response->assertNotFound();
});

test('team member returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create();

    $response = $this->actingAs($user)->get("/teams/member/{$member->id}");

    $response->assertOk();
});

test('team member redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $member = TeamMember::factory()->create();

    $response = $this->get("/teams/member/{$member->id}");

    $response->assertRedirect('/login');
});

test('team member renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create();

    $response = $this->actingAs($user)->get("/teams/member/{$member->id}");

    $response->assertViewIs('pages.teams.member');
});

test('team member passes member with related data to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create();

    Task::factory()->count(2)->create(['team_member_id' => $member->id]);
    FollowUp::factory()->count(1)->create(['team_member_id' => $member->id]);
    Bila::factory()->count(1)->create(['team_member_id' => $member->id]);
    Agreement::factory()->count(1)->create(['team_member_id' => $member->id]);

    $response = $this->actingAs($user)->get("/teams/member/{$member->id}");

    $response->assertViewHas('teamMember');
    $viewMember = $response->viewData('teamMember');
    expect($viewMember->id)->toBe($member->id);
    expect($viewMember->tasks)->toHaveCount(2);
    expect($viewMember->followUps)->toHaveCount(1);
    expect($viewMember->bilas)->toHaveCount(1);
    expect($viewMember->agreements)->toHaveCount(1);
});

test('team member returns 404 for non-existent member', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/teams/member/9999');

    $response->assertNotFound();
});
