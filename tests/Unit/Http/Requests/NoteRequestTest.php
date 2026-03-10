<?php

declare(strict_types=1);

use App\Http\Requests\NoteRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('note request rules method returns expected rule keys', function () {
    $request = new NoteRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'title',
        'content',
        'team_id',
        'team_member_id',
        'is_pinned',
    ]);
});

test('note request is authorized', function () {
    $request = new NoteRequest();

    expect($request->authorize())->toBeTrue();
});

test('note request passes with valid minimal data', function () {
    $validator = Validator::make(
        ['title' => 'My Note'],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('note request fails when title is missing', function () {
    $validator = Validator::make(
        [],
        (new NoteRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
});

test('note request fails when title exceeds max length', function () {
    $validator = Validator::make(
        ['title' => str_repeat('a', 256)],
        (new NoteRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
});

test('note request passes when title is exactly max length', function () {
    $validator = Validator::make(
        ['title' => str_repeat('a', 255)],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('note request passes when content is provided', function () {
    $validator = Validator::make(
        ['title' => 'My Note', 'content' => 'Some markdown content here.'],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('note request passes when all optional fields are null', function () {
    $validator = Validator::make(
        [
            'title' => 'My Note',
            'content' => null,
            'team_id' => null,
            'team_member_id' => null,
        ],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('note request fails when team_id references nonexistent record', function () {
    $validator = Validator::make(
        ['title' => 'My Note', 'team_id' => 9999],
        (new NoteRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_id'))->toBeTrue();
});

test('note request passes when team_id references existing team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $validator = Validator::make(
        ['title' => 'My Note', 'team_id' => $team->id],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('note request fails when team_member_id references nonexistent record', function () {
    $validator = Validator::make(
        ['title' => 'My Note', 'team_member_id' => 9999],
        (new NoteRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('note request passes when is_pinned is a boolean', function (bool $pinned) {
    $validator = Validator::make(
        ['title' => 'My Note', 'is_pinned' => $pinned],
        (new NoteRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
})->with([true, false]);
