<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages browser push subscriptions for the authenticated user.
 *
 * Requires the User model to use the HasPushSubscriptions trait from
 * laravel-notification-channels/webpush. Add to User.php:
 *   use NotificationChannels\WebPush\HasPushSubscriptions;
 */
class PushSubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * Save or update a browser push subscription for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'url'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $user->updatePushSubscription(
            endpoint: $request->string('endpoint')->toString(),
            key: $request->input('keys.p256dh'),
            token: $request->input('keys.auth'),
            contentEncoding: $request->input('content_encoding', 'aesgcm'),
        );

        return $this->successResponse(null, 'Push subscription saved.', 201);
    }

    /**
     * Remove a browser push subscription for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'url'],
        ]);

        $user = $request->user();

        $user->deletePushSubscription(
            $request->string('endpoint')->toString()
        );

        return $this->successResponse(null, 'Push subscription removed.');
    }
}
