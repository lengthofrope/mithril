<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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

test('bila index passes team and member options to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Team::factory()->create(['user_id' => $user->id]);
    TeamMember::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/bilas');

    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
});

test('bila index returns only the partial for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/bilas', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk();
    $response->assertDontSee('<!DOCTYPE html');
});

test('store creates a new bila and redirects', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->from('/bilas')
        ->post('/bilas', [
            'team_member_id' => $member->id,
            'scheduled_date' => now()->addDays(7)->toDateString(),
        ]);

    $response->assertRedirect('/bilas');
    $this->assertDatabaseHas('bilas', [
        'user_id' => $user->id,
        'team_member_id' => $member->id,
    ]);
});

test('store validates required fields', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/bilas')
        ->post('/bilas', []);

    $response->assertSessionHasErrors(['team_member_id', 'scheduled_date']);
});

test('store fires BilaScheduled event', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);

    Event::fake([App\Events\BilaScheduled::class]);

    $this->actingAs($user)->post('/bilas', [
        'team_member_id' => $member->id,
        'scheduled_date' => now()->addDays(7)->toDateString(),
    ]);

    Event::assertDispatched(App\Events\BilaScheduled::class);
});

test('destroy deletes a bila and redirects', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)
        ->from("/bilas/{$bila->id}")
        ->delete("/bilas/{$bila->id}");

    $response->assertRedirect('/bilas');
    $this->assertDatabaseMissing('bilas', ['id' => $bila->id]);
});

test('destroy returns JSON for AJAX requests', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)
        ->delete(
            "/bilas/{$bila->id}",
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('bilas', ['id' => $bila->id]);
});

test('destroy prevents deleting another users bila', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $bila = Bila::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->delete("/bilas/{$bila->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('bilas', ['id' => $bila->id]);
});

test('destroy cascades to prep items', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);
    BilaPrepItem::factory()->create(['user_id' => $user->id, 'bila_id' => $bila->id, 'team_member_id' => $member->id]);

    $this->actingAs($user)->delete("/bilas/{$bila->id}");

    $this->assertDatabaseMissing('bila_prep_items', ['bila_id' => $bila->id]);
});

test('store prep item returns JSON response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $bila = Bila::factory()->create(['user_id' => $user->id, 'team_member_id' => $member->id]);

    $response = $this->actingAs($user)
        ->postJson('/prep-items', [
            'team_member_id' => $member->id,
            'bila_id' => $bila->id,
            'content' => 'Discuss project timeline',
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseHas('bila_prep_items', ['content' => 'Discuss project timeline']);
});

test('update prep item toggles is_discussed via JSON', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $prepItem = BilaPrepItem::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'is_discussed' => false,
    ]);

    $response = $this->actingAs($user)
        ->patchJson("/prep-items/{$prepItem->id}", ['is_discussed' => true]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    expect($prepItem->fresh()->is_discussed)->toBeTrue();
});

test('destroy prep item returns JSON response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $member = TeamMember::factory()->create(['user_id' => $user->id]);
    $prepItem = BilaPrepItem::factory()->create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/prep-items/{$prepItem->id}");

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertDatabaseMissing('bila_prep_items', ['id' => $prepItem->id]);
});
