<?php

declare(strict_types=1);

use App\Http\Traits\ApiResponse;

$traitHost = new class {
    use ApiResponse;

    /**
     * @param  mixed  $data
     */
    public function callSuccessResponse(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200,
        bool $includeSavedAt = false,
    ): \Illuminate\Http\JsonResponse {
        return $this->successResponse($data, $message, $statusCode, $includeSavedAt);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public function callErrorResponse(
        string $message,
        array $errors = [],
        int $statusCode = 400,
    ): \Illuminate\Http\JsonResponse {
        return $this->errorResponse($message, $errors, $statusCode);
    }
};

test('successResponse returns json with success true and data', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(['id' => 1, 'name' => 'Test']);

    $decoded = $response->getData(true);

    expect($decoded['success'])->toBeTrue();
    expect($decoded['data'])->toBe(['id' => 1, 'name' => 'Test']);
});

test('successResponse returns http 200 by default', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse();

    expect($response->getStatusCode())->toBe(200);
});

test('successResponse respects custom status code', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(null, 'Created.', 201);

    expect($response->getStatusCode())->toBe(201);
});

test('successResponse includes message when provided', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(null, 'Created successfully.');

    $decoded = $response->getData(true);

    expect($decoded)->toHaveKey('message');
    expect($decoded['message'])->toBe('Created successfully.');
});

test('successResponse omits message key when message is empty string', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(['id' => 1]);

    $decoded = $response->getData(true);

    expect($decoded)->not->toHaveKey('message');
});

test('successResponse includes saved_at when includeSavedAt is true', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(['id' => 1], '', 200, true);

    $decoded = $response->getData(true);

    expect($decoded)->toHaveKey('saved_at');
    expect($decoded['saved_at'])->toBeString();
});

test('successResponse omits saved_at when includeSavedAt is false', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(['id' => 1], '', 200, false);

    $decoded = $response->getData(true);

    expect($decoded)->not->toHaveKey('saved_at');
});

test('successResponse saved_at is a valid ISO 8601 string', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse(null, '', 200, true);

    $decoded = $response->getData(true);

    expect((bool) strtotime($decoded['saved_at']))->toBeTrue();
});

test('successResponse data is null when not provided', function () use ($traitHost) {
    $response = $traitHost->callSuccessResponse();

    $decoded = $response->getData(true);

    expect($decoded['data'])->toBeNull();
});

test('errorResponse returns json with success false and message', function () use ($traitHost) {
    $response = $traitHost->callErrorResponse('Something went wrong.');

    $decoded = $response->getData(true);

    expect($decoded['success'])->toBeFalse();
    expect($decoded['data'])->toBeNull();
    expect($decoded['message'])->toBe('Something went wrong.');
});

test('errorResponse returns http 400 by default', function () use ($traitHost) {
    $response = $traitHost->callErrorResponse('Bad request');

    expect($response->getStatusCode())->toBe(400);
});

test('errorResponse respects custom status code', function () use ($traitHost) {
    $response = $traitHost->callErrorResponse('Not found', [], 404);

    expect($response->getStatusCode())->toBe(404);
});

test('errorResponse includes errors when provided', function () use ($traitHost) {
    $errors = ['field' => ['The field is required.']];

    $response = $traitHost->callErrorResponse('Validation failed.', $errors, 422);

    $decoded = $response->getData(true);

    expect($decoded)->toHaveKey('errors');
    expect($decoded['errors'])->toBe($errors);
});

test('errorResponse omits errors key when errors array is empty', function () use ($traitHost) {
    $response = $traitHost->callErrorResponse('An error occurred.');

    $decoded = $response->getData(true);

    expect($decoded)->not->toHaveKey('errors');
});

test('errorResponse always has success false regardless of status code', function () use ($traitHost) {
    $response = $traitHost->callErrorResponse('Error', [], 500);

    $decoded = $response->getData(true);

    expect($decoded['success'])->toBeFalse();
});
