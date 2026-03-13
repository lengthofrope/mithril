<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCalendarEventsJob;
use App\Jobs\SyncEmailsJob;
use App\Jobs\SyncJiraIssuesJob;
use App\Models\CalendarEvent;
use App\Models\Email;
use App\Models\JiraIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispatches manual sync jobs for external integrations.
 *
 * Each trigger endpoint returns the current latest synced_at timestamp.
 * The status endpoint lets the frontend poll until synced_at changes,
 * indicating the queued job has completed.
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

        $syncedAt = JiraIssue::query()->max('synced_at');

        SyncJiraIssuesJob::dispatch($user);

        return response()->json([
            'success'   => true,
            'message'   => 'Jira sync started.',
            'synced_at' => $syncedAt,
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

        $syncedAt = CalendarEvent::query()->max('synced_at');

        SyncCalendarEventsJob::dispatch($user);

        return response()->json([
            'success'   => true,
            'message'   => 'Calendar sync started.',
            'synced_at' => $syncedAt,
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

        $syncedAt = Email::query()->max('synced_at');

        SyncEmailsJob::dispatch($user);

        return response()->json([
            'success'   => true,
            'message'   => 'Email sync started.',
            'synced_at' => $syncedAt,
        ]);
    }

    /**
     * Check the latest synced_at timestamp for a given integration.
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function status(Request $_request, string $type): JsonResponse
    {
        $syncedAt = match ($type) {
            'jira'     => JiraIssue::query()->max('synced_at'),
            'calendar' => CalendarEvent::query()->max('synced_at'),
            'emails'   => Email::query()->max('synced_at'),
            default    => null,
        };

        return response()->json([
            'success'   => true,
            'synced_at' => $syncedAt,
        ]);
    }
}
