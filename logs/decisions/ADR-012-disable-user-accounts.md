## ADR-012: Disable user accounts via boolean flag and artisan commands

**Date:** 2026-03-11
**Phase:** Operations
**Tags:** backend, auth, artisan, middleware, users
**Status:** Accepted

### Context

Mithril had no mechanism to deactivate user accounts. Users could be created (`user:create`) but never disabled. The need arose for an admin-level operation to prevent specific users from logging in while preserving all their data and relationships.

Alternatives considered:
- **Soft deletes** — implies the user is "gone", requires overriding every query, breaks `BelongsToUser` scopes. Too invasive.
- **Status enum** (`active`, `suspended`, `banned`) — overengineering for a personal tool with no admin panel. Only two states are needed.

### Decision

A `boolean is_active` column (default `true`) on the `users` table controls account status. The feature is implemented through:

1. **Migration** adding `is_active` after `remember_token` with `default(true)`.
2. **Login guard** in `LoginController` — after successful `Auth::attempt()`, checks `is_active` before session regeneration. Disabled users get logged out immediately and receive a validation error.
3. **`EnsureAccountIsActive` middleware** — registered in the `web` middleware group before `EnsureTwoFactorChallengeCompleted`. Catches disabled users mid-session, logs them out, invalidates session, and redirects to login. Excludes the `logout` route.
4. **`user:disable {email}`** — sets `is_active = false`, deletes all sessions from the `sessions` table for that user.
5. **`user:enable {email}`** — sets `is_active = true`.
6. **`analytics:snapshot`** — skips disabled users to avoid recording frozen metrics.

### Consequences

- **Migration required** — adds a non-nullable boolean column with default, safe for existing data.
- **UserFactory updated** — includes `is_active => true` in default definition and a `disabled()` state method. This is required because `actingAs()` in tests uses the Eloquent model returned by `create()`, and without an explicit value the model attribute would be `null` even though the DB default is `true`.
- **Middleware ordering** — `EnsureAccountIsActive` runs before `EnsureTwoFactorChallengeCompleted` so disabled users are caught before 2FA challenge.
- **Session invalidation** — uses direct `DB::table('sessions')` delete since sessions are database-driven.
- **No data loss** — disabled users' tasks, follow-ups, bilas, and historical snapshots are preserved. Re-enabling resumes normal operation including future snapshots.

### Follow-ups / open questions

- Consider adding a `disabled_at` timestamp if audit logging becomes a requirement.
- If a web admin panel is added later, disable/enable could be exposed as UI actions instead of CLI-only.
