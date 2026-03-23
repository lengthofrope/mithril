<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Proxies health check requests to the system speech service.
 */
class SpeechServiceHealthController extends Controller
{
    use ApiResponse;

    /**
     * Check the health status of the system-wide speech service.
     *
     * @return JsonResponse
     */
    public function system(): JsonResponse
    {
        $baseUrl = config('meetings.transcription.base_url', 'http://localhost:8090');

        try {
            $response = Http::timeout(5)->get("{$baseUrl}/health");

            if ($response->successful()) {
                return $this->successResponse($response->json());
            }

            return $this->successResponse([
                'ready' => false,
                'error' => "Speech service returned status {$response->status()}.",
            ]);
        } catch (\Throwable) {
            return $this->successResponse([
                'ready' => false,
                'error' => 'Could not connect to the speech service.',
            ]);
        }
    }
}
