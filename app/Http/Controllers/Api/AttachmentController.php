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
     * Logs a system event on the parent resource when the file is removed.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = Attachment::findOrFail($id);
        $activity = $attachment->activity;
        $filename = $attachment->filename;

        $parent = $activity?->activityable;

        $attachment->delete();

        if ($activity && $activity->attachments()->count() === 0) {
            $activity->delete();
        }

        if ($parent && method_exists($parent, 'logSystemEvent')) {
            $parent->logSystemEvent(
                "Attachment removed: {$filename}",
                'attachment_removed',
                ['files' => [$filename]],
            );
        }

        return $this->successResponse(null, 'Attachment deleted.');
    }
}
