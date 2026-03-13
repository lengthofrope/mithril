## ADR-017: System broadcast notifications

**Date:** 2026-03-13
**Phase:** Operations
**Tags:** backend, frontend, artisan, notifications, broadcast
**Status:** Accepted

### Context

Administrators need a way to broadcast dismissable messages to all users — for example, notifying them to re-authenticate with Jira after infrastructure changes, or announcing new features. There was no existing notification system in Mithril.

**Alternatives considered:**
1. **Laravel's built-in `DatabaseNotification`** — Requires inserting one row per user per notification. Wasteful for broadcasts targeting all users. The `notifications` table migration doesn't exist yet.
2. **Flash-message-style approach** — Session-based, not persistent, disappears on refresh. Cannot be dismissed once and stay dismissed.
3. **Dedicated broadcast model with per-user dismissal pivot** — Single notification row, many-to-many dismissal tracking. Clean, scalable, no N-insert overhead.

Option 3 was chosen.

### Decision

- **`SystemNotification` model** — Global entity (does NOT use `BelongsToUser`). Fields: `title`, `message`, `variant` (enum: info/warning/success/error), `link_url`, `link_text`, `is_active`, `expires_at`.
- **`system_notification_dismissals` pivot table** — Tracks `(system_notification_id, user_id, dismissed_at)` with a unique constraint.
- **`NotificationVariant` enum** — Backed string enum matching `<x-ui.alert>` variants.
- **`notification:send` artisan command** — Creates a broadcast notification. All options passed via `--flags` (title, message, variant, link-url, link-text, expires-at). Title and message are required.
- **`PATCH /api/v1/system-notifications/{id}/dismiss`** — Inserts into pivot (idempotent). Uses `ApiResponse` trait.
- **Blade partial** `layouts.partials.system-notifications` — Included in `layouts.app` before `@yield('content')`. Queries active, non-dismissed notifications inline (no View Composer). Uses existing `<x-ui.alert>` component with Alpine `x-show` for smooth dismiss.
- **Alpine component** `systemNotification` — Handles dismiss via fetch PATCH, hides element with transition on success.

### Consequences

- **New migration** creates `system_notifications` and `system_notification_dismissals` tables.
- **No impact on existing models** — `SystemNotification` is entirely independent.
- Notifications auto-expire via `expires_at` column (checked in `scopeActive`).
- Deactivation is manual: set `is_active = false` via tinker or a future admin panel.
- Layout query runs on every authenticated page load — acceptable given the small table size and indexed columns.

### Follow-ups / open questions

- Consider a `notification:deactivate` command for convenience.
- If notification volume grows, the per-page query could be moved to middleware with caching.
- A future admin UI could manage notifications instead of artisan commands.
