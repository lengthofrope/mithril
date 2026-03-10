<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;

/**
 * Returns dashboard counter statistics as JSON for live UI updates.
 */
class CounterController extends Controller
{
    use ApiResponse;

    /**
     * Return the current dashboard counter values.
     *
     * @param DashboardStatsService $statsService
     * @return JsonResponse
     */
    public function __invoke(DashboardStatsService $statsService): JsonResponse
    {
        return $this->successResponse($statsService->buildStats());
    }
}
