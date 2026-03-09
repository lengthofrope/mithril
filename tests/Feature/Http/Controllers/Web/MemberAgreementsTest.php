<?php

declare(strict_types=1);

use App\Models\Agreement;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('member page shows add agreement button', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee('Add agreement');
});

test('member page shows agreement description in editable form', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $agreement = Agreement::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Deliver monthly report on time',
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee('Deliver monthly report on time');
});

test('member page shows delete button for each agreement', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    Agreement::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee('Delete agreement');
});

test('member page renders agreement manager alpine component', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee('agreementManager');
});

test('member page passes agreements as json to alpine component', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    Agreement::factory()->count(2)->create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('agreementManager');
});
