<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\Agreement;
use App\Models\FollowUp;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates a follow-up when agreement is created with follow_up_date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Deliver feature X by end of sprint',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => '2026-03-15',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'team_member_id' => $member->id,
        'description' => 'Agreement: Deliver feature X by end of sprint',
        'follow_up_date' => '2026-03-15 00:00:00',
        'status' => FollowUpStatus::Open->value,
    ]);
});

test('does not create a follow-up when agreement is created without follow_up_date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'No follow-up needed',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => null,
    ]);

    $this->assertDatabaseCount('follow_ups', 0);
});

test('creates a follow-up when agreement is updated to add follow_up_date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $agreement = Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Some agreement',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => null,
    ]);

    $agreement->update(['follow_up_date' => '2026-04-01']);

    $this->assertDatabaseHas('follow_ups', [
        'team_member_id' => $member->id,
        'description' => 'Agreement: Some agreement',
        'follow_up_date' => '2026-04-01 00:00:00',
        'status' => FollowUpStatus::Open->value,
    ]);
});

test('does not create duplicate follow-up when agreement is updated without changing follow_up_date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Original agreement',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => '2026-03-15',
    ]);

    $agreement = Agreement::first();
    $agreement->update(['description' => 'Updated description']);

    $this->assertDatabaseCount('follow_ups', 1);
});

test('follow-up user_id matches agreement user_id', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Test ownership',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => '2026-03-15',
    ]);

    $this->assertDatabaseHas('follow_ups', [
        'user_id' => $user->id,
    ]);
});

test('follow-up task_id is null for agreement-created follow-ups', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    Agreement::create([
        'user_id' => $user->id,
        'team_member_id' => $member->id,
        'description' => 'Test task_id',
        'agreed_date' => '2026-03-01',
        'follow_up_date' => '2026-03-15',
    ]);

    $followUp = FollowUp::first();
    expect($followUp->task_id)->toBeNull();
});
