<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCalendarEventsJob;
use App\Jobs\SyncEmailsJob;
use App\Jobs\SyncJiraIssuesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispatches manual sync jobs for external integrations.
 *
 * Each endpoint validates that the user has the required connection,
 * dispatches the sync job, and returns immediately.
 */
class SyncController extends Controller
{
    /**
     * Trigger a manual Jira issues sync for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function jira(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasJiraConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'Jira is not connected.',
            ], 422);
        }

        SyncJiraIssuesJob::dispatch($user);

        return response()->json([
            'success' => true,
            'message' => 'Jira sync started.',
        ]);
    }

    /**
     * Trigger a manual calendar events sync for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasMicrosoftConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account is not connected.',
            ], 422);
        }

        SyncCalendarEventsJob::dispatch($user);

        return response()->json([
            'success' => true,
            'message' => 'Calendar sync started.',
        ]);
    }

    /**
     * Trigger a manual email sync for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function emails(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasMicrosoftConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account is not connected.',
            ], 422);
        }

        SyncEmailsJob::dispatch($user);

        return response()->json([
            'success' => true,
            'message' => 'Email sync started.',
        ]);
    }
}
