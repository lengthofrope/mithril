<?php

declare(strict_types=1);

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TaskGroup;
use App\Models\TaskCategory;
use Illuminate\Support\Facades\Validator;

test('task request rules method returns expected rule keys', function () {
    $request = new TaskRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'title',
        'description',
        'priority',
        'category',
        'status',
        'deadline',
        'team_id',
        'team_member_id',
        'task_group_id',
        'task_category_id',
        'is_private',
        'sort_order',
    ]);
});

test('task request is authorized', function () {
    $request = new TaskRequest();

    expect($request->authorize())->toBeTrue();
});

test('task request passes with valid minimal data', function () {
    $validator = Validator::make(
        ['title' => 'My Task'],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('task request fails when title is missing', function () {
    $validator = Validator::make(
        [],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
});

test('task request fails when title exceeds max length', function () {
    $validator = Validator::make(
        ['title' => str_repeat('a', 256)],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
});

test('task request passes when title is exactly max length', function () {
    $validator = Validator::make(
        ['title' => str_repeat('a', 255)],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('task request fails when priority has invalid value', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'priority' => 'critical'],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('priority'))->toBeTrue();
});

test('task request passes with valid priority values', function (string $priority) {
    $validator = Validator::make(
        ['title' => 'My Task', 'priority' => $priority],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
})->with(['urgent', 'high', 'normal', 'low']);

test('task request fails when status has invalid value', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'status' => 'pending'],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('task request passes with valid status values', function (string $status) {
    $validator = Validator::make(
        ['title' => 'My Task', 'status' => $status],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
})->with(['open', 'in_progress', 'waiting', 'done']);

test('task request fails when deadline is not a valid date', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'deadline' => 'not-a-date'],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('deadline'))->toBeTrue();
});

test('task request passes when deadline is a valid date string', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'deadline' => '2026-12-31'],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('task request passes when all optional fields are null', function () {
    $validator = Validator::make(
        [
            'title' => 'My Task',
            'description' => null,
            'priority' => null,
            'category' => null,
            'status' => null,
            'deadline' => null,
            'team_id' => null,
            'team_member_id' => null,
            'task_group_id' => null,
            'task_category_id' => null,
            'sort_order' => null,
        ],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('task request fails when team_id references nonexistent record', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'team_id' => 9999],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_id'))->toBeTrue();
});

test('task request passes when team_id references existing team', function () {
    $team = Team::factory()->create();

    $validator = Validator::make(
        ['title' => 'My Task', 'team_id' => $team->id],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('task request fails when team_member_id references nonexistent record', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'team_member_id' => 9999],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('task request fails when sort_order is negative', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'sort_order' => -1],
        (new TaskRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('sort_order'))->toBeTrue();
});

test('task request passes when sort_order is zero', function () {
    $validator = Validator::make(
        ['title' => 'My Task', 'sort_order' => 0],
        (new TaskRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
