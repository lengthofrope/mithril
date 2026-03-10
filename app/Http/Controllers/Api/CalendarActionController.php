<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\BilaScheduled;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\CalendarEventLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Services\CalendarActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles creation and linking of resources (Bila, Task, FollowUp, Note) to calendar events.
 *
 * Provides three operations:
 * - prefill: returns pre-populated field data for a given resource type
 * - create: creates the resource and links it to the calendar event
 * - unlink: removes a calendar event link without deleting the underlying resource
 */
class CalendarActionController extends Controller
{
    use ApiResponse;

    /**
     * Inject the CalendarActionService.
     *
     * @param CalendarActionService $service
     */
    public function __construct(private readonly CalendarActionService $service) {}

    /**
     * Return pre-fill data for creating a resource of the given type.
     *
     * GET /api/v1/calendar-events/{calendarEvent}/prefill/{type}
     *
     * @param CalendarEvent $calendarEvent
     * @param string        $type One of: bila, task, follow-up, note.
     * @return JsonResponse
     */
    public function prefill(CalendarEvent $calendarEvent, string $type): JsonResponse
    {
        $data = $this->service->buildPrefillData($calendarEvent, $type);

        return $this->successResponse($data);
    }

    /**
     * Create a resource from the calendar event's pre-fill data and link it to the event.
     *
     * Request body fields override the pre-fill defaults (except team_member_name,
     * which is a display-only field and not persisted to any model).
     *
     * POST /api/v1/calendar-events/{calendarEvent}/create/{type}
     *
     * @param Request       $request
     * @param CalendarEvent $calendarEvent
     * @param string        $type One of: bila, task, follow-up, note.
     * @return JsonResponse
     */
    public function create(Request $request, CalendarEvent $calendarEvent, string $type): JsonResponse
    {
        $prefill = $this->service->buildPrefillData($calendarEvent, $type);
        $data    = array_merge($prefill, $request->except(['team_member_name']));
        unset($data['team_member_name']);

        if ($type === 'bila' && empty($data['team_member_id'])) {
            return $this->errorResponse(
                'Cannot create a Bila: no matching team member found from the event attendees.',
                statusCode: 422,
            );
        }

        $resource = match ($type) {
            'bila' => Bila::create([
                'team_member_id' => $data['team_member_id'],
                'scheduled_date' => $data['scheduled_date'],
            ]),
            'task' => Task::create([
                'title'          => $data['title'],
                'deadline'       => $data['deadline'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'follow-up' => FollowUp::create([
                'description'    => $data['description'],
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'note' => Note::create([
                'title'          => $data['title'],
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            default => null,
        };

        if ($resource === null) {
            return $this->errorResponse("Invalid resource type: {$type}", statusCode: 400);
        }

        if ($type === 'bila' && class_exists(BilaScheduled::class)) {
            event(new BilaScheduled($resource));
        }

        $link = $this->service->linkResource($calendarEvent, $resource);

        return $this->successResponse([
            'resource' => $resource->fresh(),
            'link'     => $link,
        ], 'Created successfully.', 201);
    }

    /**
     * Remove a link between a calendar event and a resource.
     *
     * The linked resource itself is NOT deleted — only the association is removed.
     *
     * DELETE /api/v1/calendar-events/{calendarEvent}/links/{calendarEventLink}
     *
     * @param CalendarEvent     $calendarEvent
     * @param CalendarEventLink $calendarEventLink
     * @return JsonResponse
     */
    public function unlink(CalendarEvent $calendarEvent, CalendarEventLink $calendarEventLink): JsonResponse
    {
        if ($calendarEventLink->calendar_event_id !== $calendarEvent->id) {
            return $this->errorResponse('Link does not belong to this calendar event.', statusCode: 404);
        }

        $calendarEventLink->delete();

        return $this->successResponse(null, 'Link removed.');
    }
}
