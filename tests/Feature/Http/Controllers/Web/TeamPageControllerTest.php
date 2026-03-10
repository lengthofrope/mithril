<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
    Team::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/teams');

    $response->assertViewHas('teams');
    expect($response->viewData('teams'))->toHaveCount(3);
});

test('team index includes member counts on teams', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    TeamMember::factory()->count(2)->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->get('/teams');

    $team = $response->viewData('teams')->first();
    expect($team->members_count)->toBe(2);
});

test('team index includes open tasks count per team', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

    Task::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/teams');

    $viewTeam = $response->viewData('teams')->first();
    expect($viewTeam->open_tasks_count)->toBe(2);
});

test('team show member links use correct URL format', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $response = $this->actingAs($user)->get("/teams/{$team->id}");

    $response->assertSee("/teams/member/{$member->id}");
    $response->assertDontSee("/teams/member/{$team->id}?{$member->id}");
});

test('team show returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);

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
    $team = Team::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/teams/{$team->id}");

    $response->assertViewIs('pages.teams.show');
});

test('team show passes team with members to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    TeamMember::factory()->count(2)->create(['user_id' => $user->id, 'team_id' => $team->id]);

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
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

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
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/teams/member/{$member->id}");

    $response->assertViewIs('pages.teams.member');
});

test('team member passes member with related data to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    Task::factory()->count(2)->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    FollowUp::factory()->count(1)->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    Bila::factory()->count(1)->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    Agreement::factory()->count(1)->create(['user_id' => $user->id, 'team_member_id' => $member->id, 'follow_up_date' => null]);

    $response = $this->actingAs($user)->get("/teams/member/{$member->id}");

    $response->assertViewHas('member');
    $response->assertViewHas('memberTasks');
    $response->assertViewHas('memberFollowUps');
    $response->assertViewHas('memberBilas');
    $response->assertViewHas('memberAgreements');

    $viewMember = $response->viewData('member');
    expect($viewMember->id)->toBe($member->id);
    expect($response->viewData('memberTasks'))->toHaveCount(2);
    expect($response->viewData('memberFollowUps'))->toHaveCount(1);
    expect($response->viewData('memberBilas'))->toHaveCount(1);
    expect($response->viewData('memberAgreements'))->toHaveCount(1);
});

test('team member returns 404 for non-existent member', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/teams/member/9999');

    $response->assertNotFound();
});

test('member profile page has an edit button that opens a modal with auto-save fields', function () {
    /** @var \Tests\TestCase $this */
    $user   = User::factory()->create();
    $member = TeamMember::factory()->create([
        'user_id' => $user->id,
        'name'    => 'Jane Doe',
        'role'    => 'Senior Developer',
        'email'   => 'jane@company.com',
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee('editOpen');
    $response->assertSee('Edit member', false);
    $response->assertSee("field: 'name'", false);
    $response->assertSee("field: 'role'", false);
    $response->assertSee("field: 'email'", false);
    $response->assertSee('Jane Doe');
    $response->assertSee('jane@company.com');
});

test('member profile page shows auto-save fields for bila interval and next bila date', function () {
    /** @var \Tests\TestCase $this */
    $user   = User::factory()->create();
    $member = TeamMember::factory()->create([
        'user_id'            => $user->id,
        'bila_interval_days' => 14,
        'next_bila_date'     => '2026-04-01',
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee("field: 'bila_interval_days'", false);
    $response->assertSee("field: 'next_bila_date'", false);
});

test('member profile page shows status select when status_source is manual', function () {
    /** @var \Tests\TestCase $this */
    $user   = User::factory()->create();
    $member = TeamMember::factory()->create([
        'user_id'       => $user->id,
        'status_source' => \App\Enums\StatusSource::Manual,
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertSee("field: 'status'", false);
});

test('member profile page does not show status select when status_source is microsoft', function () {
    /** @var \Tests\TestCase $this */
    $user   = User::factory()->create();
    $member = TeamMember::factory()->create([
        'user_id'       => $user->id,
        'status_source' => \App\Enums\StatusSource::Microsoft,
    ]);

    $response = $this->actingAs($user)->get(route('teams.member', $member));

    $response->assertOk();
    $response->assertDontSee("field: 'status'", false);
});

test('upload member avatar stores file and updates avatar_path', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');

    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $response = $this->actingAs($user)->post(
        route('members.avatar.upload', $member),
        ['avatar' => $file]
    );

    $response->assertRedirect();

    $member->refresh();
    expect($member->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($member->avatar_path);
});

test('upload member avatar deletes previous avatar file', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');

    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'avatar_path' => 'member-avatars/old.jpg']);

    Storage::disk('public')->put('member-avatars/old.jpg', 'old content');

    $file = UploadedFile::fake()->image('new-photo.jpg', 200, 200);

    $this->actingAs($user)->post(
        route('members.avatar.upload', $member),
        ['avatar' => $file]
    );

    Storage::disk('public')->assertMissing('member-avatars/old.jpg');

    $member->refresh();
    expect($member->avatar_path)->not->toBe('member-avatars/old.jpg');
});

test('upload member avatar validates file is an image', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');

    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)->post(
        route('members.avatar.upload', $member),
        ['avatar' => $file]
    );

    $response->assertSessionHasErrors('avatar');
});

test('upload member avatar validates max file size', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');

    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->image('huge.jpg')->size(3000);

    $response = $this->actingAs($user)->post(
        route('members.avatar.upload', $member),
        ['avatar' => $file]
    );

    $response->assertSessionHasErrors('avatar');
});

test('upload member avatar requires a file', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(
        route('members.avatar.upload', $member),
        []
    );

    $response->assertSessionHasErrors('avatar');
});

test('delete member avatar removes file and clears avatar_path', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');

    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'avatar_path' => 'member-avatars/photo.jpg']);

    Storage::disk('public')->put('member-avatars/photo.jpg', 'content');

    $response = $this->actingAs($user)->delete(
        route('members.avatar.delete', $member)
    );

    $response->assertRedirect();

    $member->refresh();
    expect($member->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('member-avatars/photo.jpg');
});

test('delete member avatar is a no-op when no avatar exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'avatar_path' => null]);

    $response = $this->actingAs($user)->delete(
        route('members.avatar.delete', $member)
    );

    $response->assertRedirect();

    $member->refresh();
    expect($member->avatar_path)->toBeNull();
});
