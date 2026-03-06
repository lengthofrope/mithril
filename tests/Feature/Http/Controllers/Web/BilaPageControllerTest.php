<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bila index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/bilas');

    $response->assertOk();
});

test('bila index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/bilas');

    $response->assertRedirect('/login');
});

test('bila index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/bilas');

    $response->assertViewIs('pages.bilas.index');
});

test('bila index passes upcoming and past to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/bilas');

    $response->assertViewHas('upcomingBilas');
    $response->assertViewHas('pastBilas');
    $response->assertViewHas('selectedTeamMemberId');
});

test('bila index splits bilas into upcoming and past groups', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->addDays(3)]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->toDateString()]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->subDays(2)]);

    $response = $this->actingAs($user)->get('/bilas');

    expect($response->viewData('upcomingBilas'))->toHaveCount(2);
    expect($response->viewData('pastBilas'))->toHaveCount(1);
});

test('bila index filters by team_member_id when provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->addDay(), 'team_member_id' => $member->id]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->addDays(2)]);

    $response = $this->actingAs($user)->get('/bilas?team_member_id=' . $member->id);

    expect($response->viewData('upcomingBilas'))->toHaveCount(1);
    expect($response->viewData('selectedTeamMemberId'))->toBe((string) $member->id);
});

test('bila show returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)->get("/bilas/{$bila->id}");

    $response->assertOk();
});

test('bila show redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $bila = Bila::factory()->create();

    $response = $this->get("/bilas/{$bila->id}");

    $response->assertRedirect('/login');
});

test('bila show renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)->get("/bilas/{$bila->id}");

    $response->assertViewIs('pages.bilas.show');
});

test('bila show passes bila with team member and prep items to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    BilaPrepItem::factory()->count(2)->create(['user_id' => $user->id, 'bila_id' => $bila->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)->get("/bilas/{$bila->id}");

    $response->assertViewHas('bila');
    $viewBila = $response->viewData('bila');
    expect($viewBila->id)->toBe($bila->id);
    expect($viewBila->teamMember)->not->toBeNull();
    expect($viewBila->prepItems)->toHaveCount(2);
});

test('bila show returns 404 for non-existent bila', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/bilas/9999');

    $response->assertNotFound();
});

test('bila show title includes team member name', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'name' => 'Alice']);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)->get("/bilas/{$bila->id}");

    $response->assertViewHas('title', 'Bila — Alice');
});
