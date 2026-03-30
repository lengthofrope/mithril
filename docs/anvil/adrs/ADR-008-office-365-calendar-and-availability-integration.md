## ADR-008: Office 365 Calendar and Availability Integration

**Date:** 2026-03-10
**Phase:** Implementation (Phase 1-3)
**Tags:** integration, backend, microsoft, oauth2, calendar, availability
**Status:** Accepted

### Context

The team lead dashboard requires two capabilities currently missing:

1. **Calendar visibility** — the team lead needs to see team members' upcoming calendar events without manually tracking them.
2. **Automatic availability sync** — member availability status should reflect real-world calendar state rather than relying entirely on manual updates.

Microsoft Graph API was chosen as the integration target because Office 365 and Outlook are the dominant tools in the target user base. The integration covers two Graph endpoints: the calendar events API and the `getSchedule` free/busy API.

Several implementation-level decisions arose during design:

- Whether to use the official Microsoft Graph SDK or raw HTTP calls.
- Where to store OAuth2 tokens.
- Whether to query the Graph API on every page load or cache results locally.
- How to run sync without blocking the request cycle.
- How to opt individual team members in or out of availability sync.
- How to map Microsoft's free/busy status values to the existing `MemberStatus` enum.

### Decision

**1. No Microsoft Graph SDK — use Laravel Http facade directly.**

The integration only touches a handful of Graph endpoints (calendar events list, `getSchedule`, token refresh). The official SDK adds approximately 5 MB of dependencies for no meaningful benefit at this scope. All REST calls go through Laravel's `Http` facade, making them testable with `Http::fake()` without any additional mocking infrastructure.

**2. Token storage on the `users` table — no separate `oauth_tokens` table.**

Microsoft Entra ID (OAuth2) is the only OAuth provider in this application. A polymorphic `oauth_tokens` table would be correct architecture for a multi-provider system but is overengineering here. Instead, four encrypted columns are added directly to `users`: `ms_access_token`, `ms_refresh_token`, `ms_token_expires_at`, and `ms_tenant_id`. Laravel's built-in column casting (`encrypted`) handles encryption at rest transparently.

**3. Local calendar cache via `calendar_events` table — no direct per-request API calls.**

Direct Graph API calls on page load would add 200–500 ms latency and would quickly exhaust per-user rate limits under normal navigation. A `calendar_events` table stores events locally. A scheduled job syncs every 15 minutes per connected user. The UI reads from the local table; staleness is acceptable given the cadence of calendar changes.

**4. Queued background jobs for all sync operations.**

Both `SyncCalendarEventsJob` and `SyncMemberAvailabilityJob` run as queued jobs dispatched by Artisan scheduler commands (`sync:calendar-events`, `sync:member-availability`). Calendar sync runs every 15 minutes; availability sync runs every 5 minutes. This keeps the request cycle clean and allows retry logic for transient Graph API failures.

**5. `status_source` field on `team_members` — per-member opt-in.**

A `status_source` enum column (`manual` | `microsoft`) on `team_members` controls whether a member's availability is governed by the Microsoft sync or remains fully manual. Defaulting to `manual` means the existing workflow is entirely unaffected until a team lead explicitly enables the integration for a member. The `SyncMemberAvailabilityJob` skips members where `status_source = manual`.

**6. Graph free/busy values mapped to existing `MemberStatus` enum.**

The Graph `getSchedule` API returns one of: `free`, `tentative`, `busy`, `oof`, `workingElsewhere`, `unknown`. These map to `MemberStatus` as follows:

| Graph value | MemberStatus |
|---|---|
| `oof` | `Absent` |
| `busy` | `PartiallyAvailable` |
| `free` | `Available` |
| `tentative` | `Available` |
| `workingElsewhere` | `Available` |
| `unknown` | no change (skip update) |

This mapping is defined as a constant array on the `MicrosoftAvailabilityMapper` value object, making it the single source of truth for the translation.

### Consequences

- **New migration:** `calendar_events` table (event_id, user_id, team_member_id, subject, start_at, end_at, is_all_day, location, organizer, synced_at).
- **Migration on `users`:** four encrypted OAuth token columns added.
- **Migration on `team_members`:** `status_source` enum column, default `manual`.
- **New service class** `MicrosoftGraphClient` wrapping Http facade calls with token refresh handling.
- **New jobs:** `SyncCalendarEventsJob`, `SyncMemberAvailabilityJob`.
- **New Artisan commands:** `sync:calendar-events`, `sync:member-availability`.
- **Scheduler registration** in `routes/console.php` (or `App\Console\Kernel`).
- Existing manual availability workflow is fully preserved for members with `status_source = manual`.
- Token refresh failures must be surfaced to the user (a UI notification or email) so they can re-authorise; silent failure would cause stale data without explanation.
- Rate limits: Microsoft Graph imposes per-user throttling; the 5-minute / 15-minute cadence is designed to stay well within default limits for a single-user dashboard scenario.

### Follow-ups / open questions

- OAuth2 callback flow (Entra ID redirect URI, controller, PKCE or not) is not detailed here — needs a dedicated implementation plan before Phase 1 starts.
- Consider whether `calendar_events` should be scoped per user (team lead) or globally (one row per team member's event regardless of which team lead triggered the sync) — relevant if multi-user support is added later.
- Token refresh failure handling strategy (notification channel, admin alert, or dashboard banner) to be decided during implementation.
- The `unknown` free/busy status currently causes a skip; revisit if Graph starts returning it for connected but unconfigured accounts.
- WebAuthn session expiry and OAuth token expiry may need coordinated handling if both are enabled for the same user.
