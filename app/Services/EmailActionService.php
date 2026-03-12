<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Email;
use App\Models\EmailLink;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles the business logic for creating and linking resources to emails.
 *
 * Mirrors the CalendarActionService pattern: resolve team members, build
 * pre-fill data, and manage polymorphic links.
 */
class EmailActionService
{
    /**
     * Resolve which team member matches the email sender.
     *
     * Compares sender_email against TeamMember.email and TeamMember.microsoft_email
     * (case-insensitive). Returns null if no match.
     *
     * @param Email $email The email to resolve the sender for.
     * @return TeamMember|null The matched team member, or null.
     */
    public function resolveTeamMember(Email $email): ?TeamMember
    {
        if ($email->sender_email === null) {
            return null;
        }

        $senderEmail = strtolower($email->sender_email);

        return TeamMember::query()
            ->where(function ($query) use ($senderEmail): void {
                $query->whereRaw('LOWER(email) = ?', [$senderEmail])
                      ->orWhereRaw('LOWER(microsoft_email) = ?', [$senderEmail]);
            })
            ->first();
    }

    /**
     * Check if the sender of an email is a member of any of the user's teams.
     *
     * @param Email $email The email to check.
     * @return bool True when the sender matches a team member.
     */
    public function senderIsTeamMember(Email $email): bool
    {
        return $this->resolveTeamMember($email) !== null;
    }

    /**
     * Build pre-fill data for creating a resource from an email.
     *
     * @param Email  $email        The source email.
     * @param string $resourceType One of: task, follow-up, note, bila.
     * @return array<string, mixed> Pre-fill data keyed by field name.
     * @throws \InvalidArgumentException When resourceType is not supported or bila has no team member.
     */
    public function buildPrefillData(Email $email, string $resourceType): array
    {
        $teamMember = $this->resolveTeamMember($email);

        $base = [
            'team_member_id'   => $teamMember?->id,
            'team_member_name' => $teamMember?->name,
        ];

        return match ($resourceType) {
            'task' => array_merge($base, [
                'title'    => $email->subject,
                'priority' => $email->importance->value,
            ]),
            'follow-up' => array_merge($base, [
                'description'    => $email->subject,
                'follow_up_date' => $email->flag_due_date?->toDateString()
                    ?? now()->addDays(3)->toDateString(),
            ]),
            'note' => array_merge($base, [
                'title'   => $email->subject,
                'content' => $email->body_preview,
            ]),
            'bila' => $this->buildBilaPrefill($email, $teamMember, $base),
            default => throw new \InvalidArgumentException("Invalid resource type: {$resourceType}"),
        };
    }

    /**
     * Create a link between an email and a resource.
     *
     * Uses firstOrCreate to prevent duplicate links.
     *
     * @param Email $email    The email to link from.
     * @param Model $resource The resource to link to.
     * @return EmailLink The existing or newly created link.
     */
    public function linkResource(Email $email, Model $resource): EmailLink
    {
        return EmailLink::firstOrCreate(
            [
                'email_id'      => $email->id,
                'linkable_type' => $resource::class,
                'linkable_id'   => $resource->getKey(),
            ],
            [
                'email_subject' => $email->subject,
            ],
        );
    }

    /**
     * Build pre-fill data for a bila resource.
     *
     * @param Email            $email      The source email.
     * @param TeamMember|null  $teamMember The resolved team member.
     * @param array<string, mixed> $base   Base pre-fill data.
     * @return array<string, mixed> Pre-fill data for bila creation.
     * @throws \InvalidArgumentException When the sender is not a team member.
     */
    private function buildBilaPrefill(Email $email, ?TeamMember $teamMember, array $base): array
    {
        if ($teamMember === null) {
            throw new \InvalidArgumentException(
                'Cannot create bila: sender is not a team member.'
            );
        }

        return array_merge($base, [
            'prep_item_content' => $email->subject,
        ]);
    }
}
