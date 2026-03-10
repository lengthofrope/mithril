<?php

declare(strict_types=1);

use App\Http\Requests\AgreementRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('agreement request rules method returns expected rule keys', function () {
    $request = new AgreementRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'team_member_id',
        'description',
        'agreed_date',
        'follow_up_date',
    ]);
});

test('agreement request is authorized', function () {
    $request = new AgreementRequest();

    expect($request->authorize())->toBeTrue();
});

test('agreement request passes with valid required data', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        [
            'team_member_id' => $member->id,
            'description' => 'Will deliver the feature by end of sprint.',
            'agreed_date' => '2026-03-01',
        ],
        (new AgreementRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('agreement request fails when team_member_id is missing', function () {
    $validator = Validator::make(
        ['description' => 'Some agreement', 'agreed_date' => '2026-03-01'],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('agreement request fails when description is missing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'agreed_date' => '2026-03-01'],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('description'))->toBeTrue();
});

test('agreement request fails when agreed_date is missing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'description' => 'Some agreement'],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('agreed_date'))->toBeTrue();
});

test('agreement request fails when team_member_id references nonexistent record', function () {
    $validator = Validator::make(
        ['team_member_id' => 9999, 'description' => 'Some agreement', 'agreed_date' => '2026-03-01'],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('agreement request fails when agreed_date is not a valid date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'description' => 'Some agreement', 'agreed_date' => 'not-a-date'],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('agreed_date'))->toBeTrue();
});

test('agreement request fails when follow_up_date is not a valid date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        [
            'team_member_id' => $member->id,
            'description' => 'Some agreement',
            'agreed_date' => '2026-03-01',
            'follow_up_date' => 'not-a-date',
        ],
        (new AgreementRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('follow_up_date'))->toBeTrue();
});

test('agreement request passes when follow_up_date is a valid date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        [
            'team_member_id' => $member->id,
            'description' => 'Some agreement',
            'agreed_date' => '2026-03-01',
            'follow_up_date' => '2026-04-01',
        ],
        (new AgreementRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('agreement request passes when follow_up_date is null', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        [
            'team_member_id' => $member->id,
            'description' => 'Some agreement',
            'agreed_date' => '2026-03-01',
            'follow_up_date' => null,
        ],
        (new AgreementRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
