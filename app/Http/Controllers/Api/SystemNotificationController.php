<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles system notification dismiss actions.
 */
class SystemNotificationController extends Controller
{
    use ApiResponse;

    /**
     * Dismiss a system notification for the authenticated user.
     */
    public function dismiss(Request $request, SystemNotification $systemNotification): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$systemNotification->isDismissedBy($request->user())) {
            $systemNotification->dismissals()->attach($userId, [
                'dismissed_at' => now(),
            ]);
        }

        return $this->successResponse(message: 'Notification dismissed.');
    }
}
