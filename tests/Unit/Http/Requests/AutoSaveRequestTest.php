<?php

declare(strict_types=1);

use App\Http\Requests\AutoSaveRequest;
use Illuminate\Support\Facades\Validator;

test('auto save request rules method returns expected rule keys', function () {
    $request = new AutoSaveRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'model',
        'id',
        'field',
        'value',
    ]);
});

test('auto save request is authorized', function () {
    $request = new AutoSaveRequest();

    expect($request->authorize())->toBeTrue();
});

test('auto save request passes with valid data', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'field' => 'title', 'value' => 'New Title'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('auto save request passes when value is null', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'field' => 'description', 'value' => null],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('auto save request passes when value is an empty string', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'field' => 'description', 'value' => ''],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('auto save request fails when model is missing', function () {
    $validator = Validator::make(
        ['id' => 1, 'field' => 'title', 'value' => 'New Title'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('model'))->toBeTrue();
});

test('auto save request fails when id is missing', function () {
    $validator = Validator::make(
        ['model' => 'task', 'field' => 'title', 'value' => 'New Title'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('id'))->toBeTrue();
});

test('auto save request fails when field is missing', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'value' => 'New Title'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('field'))->toBeTrue();
});

test('auto save request fails when value key is entirely absent', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'field' => 'title'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('value'))->toBeTrue();
});

test('auto save request fails when model contains disallowed characters', function () {
    $validator = Validator::make(
        ['model' => 'task model', 'id' => 1, 'field' => 'title', 'value' => 'test'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('model'))->toBeTrue();
});

test('auto save request fails when field contains disallowed characters', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 1, 'field' => 'title field', 'value' => 'test'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('field'))->toBeTrue();
});

test('auto save request fails when id is zero', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => 0, 'field' => 'title', 'value' => 'test'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('id'))->toBeTrue();
});

test('auto save request fails when id is negative', function () {
    $validator = Validator::make(
        ['model' => 'task', 'id' => -1, 'field' => 'title', 'value' => 'test'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('id'))->toBeTrue();
});

test('auto save request passes when model uses underscores and dashes', function () {
    $validator = Validator::make(
        ['model' => 'team_member', 'id' => 5, 'field' => 'follow-up', 'value' => 'test'],
        (new AutoSaveRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
