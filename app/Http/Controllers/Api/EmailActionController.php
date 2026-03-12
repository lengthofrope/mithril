<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\BilaScheduled;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
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
            ->where('is_dismissed', false)
            ->orderByDesc('received_at');

        if ($request->has('source') && $request->input('source') !== 'all') {
            $source = $request->input('source');
            $query->whereJsonContains('sources', $source);
        }

        $emails = $query->get()->map(fn (Email $email): array => array_merge(
            $email->toArray(),
            ['links' => $email->emailLinks->toArray()],
            $this->service->buildSenderDisplayData($email),
        ));

        return $this->successResponse($emails);
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
            ->where('is_dismissed', false)
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
            'bila' => $this->createBila($email, $data),
            'task' => Task::create([
                'title'          => $data['title'],
                'priority'       => $data['priority'] ?? null,
                'team_member_id' => $data['team_member_id'] ?? null,
            ]),
            'follow-up' => FollowUp::create([
                'description'    => $data['description'],
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
     * Mark an email as dismissed in Mithril.
     *
     * POST /api/v1/emails/{email}/dismiss
     *
     * @param Email $email
     * @return JsonResponse
     */
    public function dismiss(Email $email): JsonResponse
    {
        $email->update(['is_dismissed' => true]);

        return $this->successResponse(null, 'Email dismissed.');
    }

    /**
     * Restore a dismissed email back to the active list.
     *
     * POST /api/v1/emails/{email}/undismiss
     *
     * @param Email $email
     * @return JsonResponse
     */
    public function undismiss(Email $email): JsonResponse
    {
        $email->update(['is_dismissed' => false]);

        return $this->successResponse(null, 'Email restored.');
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
     * Create a Bila resource from an email.
     *
     * If an upcoming Bila exists for the team member, adds a prep item instead.
     *
     * @param Email              $email The source email.
     * @param array<string, mixed> $data The merged prefill + request data.
     * @return Bila The created or existing Bila.
     */
    private function createBila(Email $email, array $data): Bila
    {
        $existingBila = Bila::query()
            ->where('team_member_id', $data['team_member_id'])
            ->where('is_done', false)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if ($existingBila) {
            BilaPrepItem::create([
                'bila_id' => $existingBila->id,
                'content' => $data['prep_item_content'] ?? $email->subject,
            ]);

            return $existingBila;
        }

        $bila = Bila::create([
            'team_member_id' => $data['team_member_id'],
            'scheduled_date' => now()->addDays(7)->toDateString(),
        ]);

        BilaPrepItem::create([
            'bila_id' => $bila->id,
            'content' => $data['prep_item_content'] ?? $email->subject,
        ]);

        if (class_exists(BilaScheduled::class)) {
            event(new BilaScheduled($bila));
        }

        return $bila;
    }
}
