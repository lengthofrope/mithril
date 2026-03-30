## ADR-009: Auto-detect O365 status source from member email

**Date:** 2026-03-10
**Phase:** Implementation (Phase 1-3)
**Tags:** integration, backend, microsoft, ux, auto-detection
**Status:** Accepted

### Context

The O365 availability sync required users to manually:
1. Enter a separate `microsoft_email` field on each team member
2. Manually select `status_source` between "Manual" and "Auto (Office 365)"

This created unnecessary friction. The team member already has an `email` field. When a user has connected their Microsoft account, the system can automatically check whether a team member's email is a known O365 mailbox.

### Decision

When the `email` field on a team member is updated:
- If the authenticated user has a Microsoft connection, probe the Graph API `/me/calendar/getSchedule` endpoint with the email to determine if it's a valid O365 mailbox.
- If the email resolves (no error in the schedule response), auto-set `status_source` to `microsoft` and copy the email to `microsoft_email`.
- If the email does not resolve, or the user has no Microsoft connection, or the Graph API call fails, set `status_source` to `manual` and clear `microsoft_email`.
- The `status_source` and `microsoft_email` fields are no longer directly settable via the API.
- The separate "Microsoft email" input and "Status source" dropdown are removed from the UI.

New method: `MicrosoftGraphService::isKnownMicrosoftUser(User $user, string $email): bool` — uses `getSchedule` with a 1-minute window to check if an email resolves in the tenant.

### Deviation from plan

The original plan had `microsoft_email` as a separate user-facing field. This ADR simplifies the UX by deriving it from the existing `email` field.

### Consequences

- **UX improvement:** Two manual steps reduced to zero. Setting an email on a team member is all that's needed.
- **Backward compatibility:** The `microsoft_email` column remains in the database and is still used by `SyncMemberAvailabilityJob`. It is now auto-populated, not user-set.
- **Extra API call:** Each email change on a team member triggers one Graph API call when the user has a Microsoft connection. Graceful fallback on failure.
- **No migration needed:** No schema changes — only behavioral changes in the controller.

### Follow-ups / open questions

- Consider caching the O365 lookup result to avoid repeated Graph calls when the same email is re-saved.
- If the tenant changes or permissions are revoked, existing `microsoft_email` values remain until the email is re-saved.
