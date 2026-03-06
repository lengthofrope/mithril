<?php

declare(strict_types=1);

use App\Http\Requests\ReorderRequest;
use Illuminate\Support\Facades\Validator;

test('reorder request rules method returns expected rule keys', function () {
    $request = new ReorderRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKeys([
        'model',
        'items',
        'items.*.id',
        'items.*.sort_order',
    ]);
});

test('reorder request is authorized', function () {
    $request = new ReorderRequest();

    expect($request->authorize())->toBeTrue();
});

test('reorder request passes with valid data', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 1, 'sort_order' => 0],
                ['id' => 2, 'sort_order' => 1],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('reorder request fails when model is missing', function () {
    $validator = Validator::make(
        [
            'items' => [
                ['id' => 1, 'sort_order' => 0],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('model'))->toBeTrue();
});

test('reorder request fails when items array is missing', function () {
    $validator = Validator::make(
        ['model' => 'task'],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items'))->toBeTrue();
});

test('reorder request fails when items array is empty', function () {
    $validator = Validator::make(
        ['model' => 'task', 'items' => []],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items'))->toBeTrue();
});

test('reorder request fails when items is not an array', function () {
    $validator = Validator::make(
        ['model' => 'task', 'items' => 'not-an-array'],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items'))->toBeTrue();
});

test('reorder request fails when item id is missing', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['sort_order' => 0],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.id'))->toBeTrue();
});

test('reorder request fails when item sort_order is missing', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 1],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.sort_order'))->toBeTrue();
});

test('reorder request fails when item id is zero', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 0, 'sort_order' => 0],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.id'))->toBeTrue();
});

test('reorder request fails when item sort_order is negative', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 1, 'sort_order' => -1],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('items.0.sort_order'))->toBeTrue();
});

test('reorder request passes when sort_order is zero', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 1, 'sort_order' => 0],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});

test('reorder request fails when model contains disallowed characters', function () {
    $validator = Validator::make(
        [
            'model' => 'task model',
            'items' => [
                ['id' => 1, 'sort_order' => 0],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('model'))->toBeTrue();
});

test('reorder request validates multiple items independently', function () {
    $validator = Validator::make(
        [
            'model' => 'task',
            'items' => [
                ['id' => 1, 'sort_order' => 0],
                ['id' => 2, 'sort_order' => 1],
                ['id' => 3, 'sort_order' => 2],
            ],
        ],
        (new ReorderRequest())->rules()
    );

    expect($validator->passes())->toBeTrue();
});
