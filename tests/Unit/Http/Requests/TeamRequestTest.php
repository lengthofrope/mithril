<?php

declare(strict_types=1);

use App\Http\Requests\TeamRequest;
use Illuminate\Support\Facades\Validator;

test('team request rules method returns expected rule keys', function () {
    $request = new TeamRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'name',
        'description',
        'color',
        'sort_order',
    ]);
});

test('team request is authorized', function () {
    $request = new TeamRequest();

    expect($request->authorize())->toBeTrue();
});

test('team request passes with valid minimal data', function () {
    $validator = Validator::make(
        ['name' => 'Engineering'],
        (new TeamRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team request fails when name is missing', function () {
    $validator = Validator::make(
        [],
        (new TeamRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('team request fails when name exceeds max length', function () {
    $validator = Validator::make(
        ['name' => str_repeat('a', 256)],
        (new TeamRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('team request passes when name is exactly max length', function () {
    $validator = Validator::make(
        ['name' => str_repeat('a', 255)],
        (new TeamRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team request passes when color is provided within max length', function () {
    $validator = Validator::make(
        ['name' => 'Engineering', 'color' => '#ff5733'],
        (new TeamRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team request fails when color exceeds max length', function () {
    $validator = Validator::make(
        ['name' => 'Engineering', 'color' => str_repeat('a', 21)],
        (new TeamRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('color'))->toBeTrue();
});

test('team request passes when all optional fields are null', function () {
    $validator = Validator::make(
        [
            'name' => 'Engineering',
            'description' => null,
            'color' => null,
            'sort_order' => null,
        ],
        (new TeamRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('team request fails when sort_order is negative', function () {
    $validator = Validator::make(
        ['name' => 'Engineering', 'sort_order' => -1],
        (new TeamRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('sort_order'))->toBeTrue();
});

test('team request passes when sort_order is zero', function () {
    $validator = Validator::make(
        ['name' => 'Engineering', 'sort_order' => 0],
        (new TeamRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
