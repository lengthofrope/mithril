<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing individual file attachments.
 */
class AttachmentController extends Controller
{
    use ApiResponse;

    /**
     * Delete an attachment and clean up its parent activity if orphaned.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);
        $activity = $attachment->activity;

        $attachment->delete();

        if ($activity && $activity->attachments()->count() === 0) {
            $activity->delete();
        }

        return $this->successResponse(null, 'Attachment deleted.');
    }
}
