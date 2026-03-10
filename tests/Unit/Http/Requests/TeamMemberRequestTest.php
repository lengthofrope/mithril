<?php

declare(strict_types=1);

use App\Http\Requests\TeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('team member request rules method returns expected rule keys', function () {
    $request = new TeamMemberRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'team_id',
        'name',
        'role',
        'email',
        'notes',
        'status',
        'avatar_path',
        'bila_interval_days',
        'next_bila_date',
        'sort_order',
    ]);
});

test('team member request is authorized', function () {
    $request = new TeamMemberRequest();

    expect($request->authorize())->toBeTrue();
});

test('team member request passes with valid required data', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team member request fails when name is missing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('team member request fails when team_id is missing', function () {
    $validator = Validator::make(
        ['name' => 'Jane Doe'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_id'))->toBeTrue();
});

test('team member request fails when team_id references nonexistent team', function () {
    $validator = Validator::make(
        ['team_id' => 9999, 'name' => 'Jane Doe'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_id'))->toBeTrue();
});

test('team member request fails when name exceeds max length', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => str_repeat('a', 256)],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('team member request fails when status has invalid value', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'status' => 'unavailable'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('team member request passes with valid status values', function (string $status) {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'status' => $status],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
})->with(['available', 'absent', 'partially_available']);

test('team member request fails when email is invalid', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'email' => 'not-an-email'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

test('team member request passes when email is valid', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team member request fails when bila_interval_days is less than one', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'bila_interval_days' => 0],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('bila_interval_days'))->toBeTrue();
});

test('team member request passes when bila_interval_days is one or more', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'bila_interval_days' => 14],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team member request fails when next_bila_date is not a valid date', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['team_id' => $team->id, 'name' => 'Jane Doe', 'next_bila_date' => 'not-a-date'],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('next_bila_date'))->toBeTrue();
});

test('team member request passes when all optional fields are null', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        [
            'team_id' => $team->id,
            'name' => 'Jane Doe',
            'role' => null,
            'email' => null,
            'notes' => null,
            'status' => null,
            'avatar_path' => null,
            'bila_interval_days' => null,
            'next_bila_date' => null,
            'sort_order' => null,
        ],
        (new TeamMemberRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
