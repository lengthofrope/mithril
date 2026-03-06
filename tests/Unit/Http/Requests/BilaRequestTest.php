<?php

declare(strict_types=1);

use App\Http\Requests\BilaRequest;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Validator;

test('bila request rules method returns expected rule keys', function () {
    $request = new BilaRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'team_member_id',
        'scheduled_date',
        'notes',
    ]);
});

test('bila request is authorized', function () {
    $request = new BilaRequest();

    expect($request->authorize())->toBeTrue();
});

test('bila request passes with valid required data', function () {
    $team = Team::factory()->create();
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'scheduled_date' => '2026-04-01'],
        (new BilaRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('bila request fails when team_member_id is missing', function () {
    $validator = Validator::make(
        ['scheduled_date' => '2026-04-01'],
        (new BilaRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('bila request fails when scheduled_date is missing', function () {
    $team = Team::factory()->create();
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    $validator = Validator::make(
        ['team_member_id' => $member->id],
        (new BilaRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('scheduled_date'))->toBeTrue();
});

test('bila request fails when team_member_id references nonexistent record', function () {
    $validator = Validator::make(
        ['team_member_id' => 9999, 'scheduled_date' => '2026-04-01'],
        (new BilaRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('bila request fails when scheduled_date is not a valid date', function () {
    $team = Team::factory()->create();
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'scheduled_date' => 'not-a-date'],
        (new BilaRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('scheduled_date'))->toBeTrue();
});

test('bila request passes when notes are provided', function () {
    $team = Team::factory()->create();
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    $validator = Validator::make(
        [
            'team_member_id' => $member->id,
            'scheduled_date' => '2026-04-01',
            'notes' => 'These are my prep notes for the bila.',
        ],
        (new BilaRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('bila request passes when notes are null', function () {
    $team = Team::factory()->create();
    $member = TeamMember::factory()->create(['team_id' => $team->id]);

    $validator = Validator::make(
        ['team_member_id' => $member->id, 'scheduled_date' => '2026-04-01', 'notes' => null],
        (new BilaRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
