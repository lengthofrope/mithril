<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * API controller for managing activity feed entries on parent models.
 *
 * Supports comments, links, and file attachments on tasks, follow-ups,
 * notes, and bilas via a shared polymorphic endpoint.
 */
class ActivityController extends Controller
{
    use ApiResponse;

    /**
     * Map of URL type segments to their fully qualified model class names.
     *
     * @var array<string, class-string<Model>>
     */
    private array $modelMap = [
        'tasks'       => Task::class,
        'follow-ups'  => FollowUp::class,
        'notes'       => Note::class,
        'bilas'       => Bila::class,
    ];

    /**
     * Create a new activity on the specified parent resource.
     *
     * @param ActivityRequest $request
     * @param string $type
     * @param int $id
     * @return JsonResponse
     */
    public function store(ActivityRequest $request, string $type, int $id): JsonResponse
    {
        $parent = $this->resolveParentOrFail($type, $id);

        if ($parent === null) {
            return $this->errorResponse('Not found.', [], 404);
        }

        $validated = $request->validated();
        $activityType = ActivityType::from($validated['type']);

        $activity = match ($activityType) {
            ActivityType::Comment    => $this->createComment($parent, $validated),
            ActivityType::Link       => $this->createLink($parent, $validated),
            ActivityType::Attachment => $this->createAttachment($parent, $request),
            default                  => null,
        };

        if ($activity instanceof JsonResponse) {
            return $activity;
        }

        return $this->successResponse($activity, 'Activity created.', 201);
    }

    /**
     * Update the body of an existing activity.
     *
     * @param Request $request
     * @param string $type
     * @param int $id
     * @param int $activityId
     * @return JsonResponse
     */
    public function update(Request $request, string $type, int $id, int $activityId): JsonResponse
    {
        $parent = $this->resolveParentOrFail($type, $id);

        if ($parent === null) {
            return $this->errorResponse('Not found.', [], 404);
        }

        $activity = Activity::where('id', $activityId)
            ->where('activityable_type', get_class($parent))
            ->where('activityable_id', $parent->id)
            ->firstOrFail();

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        $activity->update(['body' => $validated['body'] ?? null]);

        return $this->successResponse($activity->fresh(), 'Activity updated.');
    }

    /**
     * Delete an activity and its associated attachments and files.
     *
     * @param Request $request
     * @param string $type
     * @param int $id
     * @param int $activityId
     * @return JsonResponse
     */
    public function destroy(Request $request, string $type, int $id, int $activityId): JsonResponse
    {
        $parent = $this->resolveParentOrFail($type, $id);

        if ($parent === null) {
            return $this->errorResponse('Not found.', [], 404);
        }

        $activity = Activity::where('id', $activityId)
            ->where('activityable_type', get_class($parent))
            ->where('activityable_id', $parent->id)
            ->firstOrFail();

        $filenames = $activity->attachments->pluck('filename')->all();

        $activity->attachments()->each(fn (Attachment $attachment) => $attachment->delete());
        $activity->delete();

        if (!empty($filenames) && method_exists($parent, 'logSystemEvent')) {
            $label = count($filenames) === 1
                ? "Attachment removed: {$filenames[0]}"
                : 'Attachments removed: ' . implode(', ', $filenames);

            $parent->logSystemEvent($label, 'attachment_removed', ['files' => $filenames]);
        }

        return $this->successResponse(null, 'Activity deleted.');
    }

    /**
     * Resolve the parent model from the type segment and ID, or return null if not found.
     *
     * BelongsToUser global scope ensures only the authenticated user's records are found.
     *
     * @param string $type
     * @param int $id
     * @return Model|null
     */
    private function resolveParentOrFail(string $type, int $id): ?Model
    {
        if (!isset($this->modelMap[$type])) {
            abort(404);
        }

        $modelClass = $this->modelMap[$type];

        return $modelClass::findOrFail($id);
    }

    /**
     * Create a comment activity on the given parent.
     *
     * @param Model $parent
     * @param array<string, mixed> $validated
     * @return Activity
     */
    private function createComment(Model $parent, array $validated): Activity
    {
        return Activity::create([
            'activityable_type' => get_class($parent),
            'activityable_id'   => $parent->id,
            'type'              => ActivityType::Comment,
            'body'              => $validated['body'] ?? null,
        ]);
    }

    /**
     * Create a link activity on the given parent.
     *
     * @param Model $parent
     * @param array<string, mixed> $validated
     * @return Activity
     */
    private function createLink(Model $parent, array $validated): Activity
    {
        return Activity::create([
            'activityable_type' => get_class($parent),
            'activityable_id'   => $parent->id,
            'type'              => ActivityType::Link,
            'body'              => $validated['body'] ?? null,
            'metadata'          => [
                'url'   => $validated['url'],
                'title' => $validated['link_title'] ?? null,
            ],
        ]);
    }

    /**
     * Create an attachment activity on the given parent, storing uploaded files.
     *
     * Returns a JsonResponse on quota violations, otherwise returns the created Activity.
     *
     * @param Model $parent
     * @param ActivityRequest $request
     * @return Activity|JsonResponse
     */
    private function createAttachment(Model $parent, ActivityRequest $request): Activity|JsonResponse
    {
        $files = $request->file('files', []);
        $incomingSize = array_sum(array_map(fn ($file) => $file->getSize(), $files));
        $currentUsage = Attachment::where('user_id', auth()->id())->sum('size');
        $maxBytes = config('attachments.max_storage_mb') * 1024 * 1024;

        if (($currentUsage + $incomingSize) > $maxBytes) {
            return $this->errorResponse('Storage quota exceeded.', [], 422);
        }

        $activity = Activity::create([
            'activityable_type' => get_class($parent),
            'activityable_id'   => $parent->id,
            'type'              => ActivityType::Attachment,
        ]);

        $directory = 'attachments/' . now()->format('Y/m');

        foreach ($files as $file) {
            $uniqueName = uniqid('', true) . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($directory, $uniqueName, 'local');

            Attachment::create([
                'activity_id' => $activity->id,
                'filename'    => $file->getClientOriginalName(),
                'path'        => $path,
                'disk'        => 'local',
                'mime_type'   => $file->getMimeType() ?? $file->getClientMimeType(),
                'size'        => $file->getSize(),
            ]);
        }

        return $activity->load('attachments');
    }
}
