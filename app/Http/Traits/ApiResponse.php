<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Provides standardized JSON API response helpers.
 *
 * All responses follow the format:
 * { success: bool, data: mixed, message?: string, saved_at?: string }
 */
trait ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param bool $includeSavedAt
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200,
        bool $includeSavedAt = false,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        if ($includeSavedAt) {
            $payload['saved_at'] = now()->toIso8601String();
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param array<string, mixed> $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        array $errors = [],
        int $statusCode = 400,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'data' => null,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }
}
