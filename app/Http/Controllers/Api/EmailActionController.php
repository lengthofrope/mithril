<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\MeetingScheduled;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingPrepItem;
use App\Models\Note;
use App\Models\Task;
use App\Services\EmailActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles email-related API actions: listing, prefilling, creating resources, and managing links.
 */
class EmailActionController extends Controller
{
    use ApiResponse;

    /**
     * Inject the EmailActionService.
     *
     * @param EmailActionService $service
     */
    public function __construct(private readonly EmailActionService $service) {}

    /**
     * List the user's cached emails, optionally filtered by source.
     *
     * GET /api/v1/emails
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Email::query()
            ->with('emailLinks')
            ->orderByDesc('received_at');

        if ($request->has('source') && $request->input('source') !== 'all') {
            $source = $request->input('source');
            $query->whereJsonContains('sources', $source);
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $paginator = $query->paginate($perPage);

        $transformedItems = collect($paginator->items())->map(fn (Email $email): array => array_merge(
            $email->toArray(),
            ['links' => $email->emailLinks->toArray()],
            $this->service->buildSenderDisplayData($email),
        ))->all();

        return $this->paginatedSuccessResponse($paginator, $transformedItems);
    }

    /**
     * Return all flagged emails for the dashboard widget.
     *
     * GET /api/v1/emails/dashboard
     *
     * @return JsonResponse
     */
    public function dashboard(): JsonResponse
    {
        $emails = Email::query()
            ->where('is_flagged', true)
            ->orderByRaw('flag_due_date IS NULL, flag_due_date ASC')
            ->get()
            ->map(fn (Email $email): array => array_merge(
                $email->toArray(),
                ['sender_is_team_member' => $this->service->senderIsTeamMember($email)],
            ));

        return $this->successResponse($emails);
    }

    /**
     * Return pre-fill data for creating a resource from an email.
     *
     * GET /api/v1/emails/{email}/prefill/{type}
     *
     * @param Email  $email
     * @param string $type
     * @return JsonResponse
     */
    public function prefill(Email $email, string $type): JsonResponse
    {
        try {
            $data = $this->service->buildPrefillData($email, $type);

            return $this->successResponse($data);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), statusCode: 422);
        }
    }

    /**
     * Create a resource from an email and link it.
     *
     * POST /api/v1/emails/{email}/create/{type}
     *
     * @param Request $request
     * @param Email   $email
     * @param string  $type
     * @return JsonResponse
     */
    public function create(Request $request, Email $email, string $type): JsonResponse
    {
        try {
            $prefill = $this->service->buildPrefillData($email, $type);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), statusCode: 422);
        }

        $data = array_merge($prefill, $request->except(['team_member_name']));
        unset($data['team_member_name']);

        $resource = match ($type) {
            'meeting' => $this->createMeeting($email, $data),
            'task' => Task::create([
                'title'          => $data['title'],
                'priority'       => $data['priority'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'follow-up' => FollowUp::create([
                'title'          => $data['description'],
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'note' => Note::create([
                'title'          => $data['title'],
                'content'        => $data['content'] ?? '',
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            default => null,
        };

        if ($resource === null) {
            return $this->errorResponse("Invalid resource type: {$type}", statusCode: 400);
        }

        $link = $this->service->linkResource($email, $resource);

        return $this->successResponse([
            'resource' => $resource->fresh(),
            'link'     => $link,
        ], 'Created successfully.', 201);
    }

    /**
     * Remove a link between an email and a resource.
     *
     * DELETE /api/v1/emails/{email}/links/{emailLink}
     *
     * @param Email     $email
     * @param EmailLink $emailLink
     * @return JsonResponse
     */
    public function unlink(Email $email, EmailLink $emailLink): JsonResponse
    {
        if ($emailLink->email_id !== $email->id) {
            return $this->errorResponse('Link does not belong to this email.', statusCode: 404);
        }

        $emailLink->delete();

        return $this->successResponse(null, 'Link removed.');
    }

    /**
     * Create a Meeting resource from an email.
     *
     * If an upcoming meeting exists for the team member, adds a prep item instead.
     *
     * @param Email                $email The source email.
     * @param array<string, mixed> $data  The merged prefill + request data.
     * @return Meeting The created or existing Meeting.
     */
    private function createMeeting(Email $email, array $data): Meeting
    {
        $existingMeeting = !empty($data['team_member_id'])
            ? Meeting::query()
                ->whereHas('attendees', fn ($q) => $q->where('team_member_id', $data['team_member_id']))
                ->where('is_done', false)
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->first()
            : null;

        if ($existingMeeting) {
            MeetingPrepItem::create([
                'meeting_id' => $existingMeeting->id,
                'content' => $data['prep_item_content'] ?? $email->subject,
            ]);

            return $existingMeeting;
        }

        $meeting = Meeting::create([
            'title' => $email->subject ?? 'Meeting',
            'type' => 'one_on_one',
            'scheduled_at' => now()->addDays(7),
        ]);

        if (!empty($data['team_member_id'])) {
            $meeting->attendees()->attach($data['team_member_id']);
        }

        MeetingPrepItem::create([
            'meeting_id' => $meeting->id,
            'content' => $data['prep_item_content'] ?? $email->subject,
        ]);

        event(new MeetingScheduled($meeting));

        return $meeting;
    }
}
