<?php

declare(strict_types=1);

use App\Http\Requests\FollowUpRequest;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Validator;

test('follow up request rules method returns expected rule keys', function () {
    $request = new FollowUpRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'task_id',
        'team_member_id',
        'description',
        'waiting_on',
        'follow_up_date',
        'snoozed_until',
        'status',
    ]);
});

test('follow up request is authorized', function () {
    $request = new FollowUpRequest();

    expect($request->authorize())->toBeTrue();
});

test('follow up request passes with valid required data', function () {
    $validator = Validator::make(
        ['description' => 'Waiting for feedback on the proposal.'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('follow up request fails when description is missing', function () {
    $validator = Validator::make(
        [],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('description'))->toBeTrue();
});

test('follow up request fails when status has invalid value', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'status' => 'pending'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('follow up request passes with valid status values', function (string $status) {
    $validator = Validator::make(
        ['description' => 'Some description', 'status' => $status],
        (new FollowUpRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
})->with(['open', 'snoozed', 'done']);

test('follow up request fails when follow_up_date is not a valid date', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'follow_up_date' => 'not-a-date'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('follow_up_date'))->toBeTrue();
});

test('follow up request passes when follow_up_date is a valid date', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'follow_up_date' => '2026-04-15'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('follow up request fails when snoozed_until is not a valid date', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'snoozed_until' => 'not-a-date'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('snoozed_until'))->toBeTrue();
});

test('follow up request passes when snoozed_until is a valid date', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'snoozed_until' => '2026-04-20'],
        (new FollowUpRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('follow up request fails when task_id references nonexistent record', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'task_id' => 9999],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('task_id'))->toBeTrue();
});

test('follow up request fails when team_member_id references nonexistent record', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'team_member_id' => 9999],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('team_member_id'))->toBeTrue();
});

test('follow up request passes when all optional fields are null', function () {
    $validator = Validator::make(
        [
            'description' => 'Some description',
            'task_id' => null,
            'team_member_id' => null,
            'waiting_on' => null,
            'follow_up_date' => null,
            'snoozed_until' => null,
            'status' => null,
        ],
        (new FollowUpRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('follow up request converts empty string foreign keys to null via prepareForValidation', function () {
    $request = new FollowUpRequest();
    $request->merge([
        'description' => 'Test',
        'task_id' => '',
        'team_member_id' => '',
    ]);

    $reflection = new ReflectionMethod($request, 'prepareForValidation');
    $reflection->invoke($request);

    expect($request->input('task_id'))->toBeNull();
    expect($request->input('team_member_id'))->toBeNull();
});

test('follow up request preserves valid foreign key values via prepareForValidation', function () {
    $request = new FollowUpRequest();
    $request->merge([
        'description' => 'Test',
        'task_id' => 5,
        'team_member_id' => 3,
    ]);

    $reflection = new ReflectionMethod($request, 'prepareForValidation');
    $reflection->invoke($request);

    expect($request->input('task_id'))->toBe(5);
    expect($request->input('team_member_id'))->toBe(3);
});

test('follow up request fails when waiting_on exceeds max length', function () {
    $validator = Validator::make(
        ['description' => 'Some description', 'waiting_on' => str_repeat('a', 256)],
        (new FollowUpRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('waiting_on'))->toBeTrue();
});
