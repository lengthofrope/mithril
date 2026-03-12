## ADR-014: Configurable dashboard upcoming items

**Date:** 2026-03-12
**Phase:** Dashboard
**Tags:** backend, frontend, dashboard, settings, user-preferences
**Status:** Accepted

### Context

The three dashboard widgets (Tasks due today, Follow-ups needing attention, Bilas today) only showed items for the current day. The user wanted the option to also display a configurable number of upcoming (future) items per widget, with a visual separator between today's items and upcoming ones. This needed to be configurable per-widget in settings.

Alternatives considered:
- **JSON column on users table** — more flexible but harder to validate and query
- **Separate user_preferences table** — overkill for 3 simple values
- **Three nullable integer columns on users** — simplest, follows existing pattern (timezone, prune_after_days)

### Decision

Three nullable `unsignedTinyInteger` columns added to `users`: `dashboard_upcoming_tasks`, `dashboard_upcoming_follow_ups`, `dashboard_upcoming_bilas`. When `null`, the widget shows today-only (current behavior). When set (0–20), the widget fetches up to N additional future items.

- **DashboardController** has a new `buildUpcomingSection()` method that queries future items per widget, limited by the user's setting
- **Widget titles change dynamically** based on whether upcoming items actually exist in the result: "Tasks due today" becomes "Upcoming tasks", etc.
- **Elvish divider with leaf** separates today's items from upcoming items visually
- **Settings page** has a new "Dashboard widgets" card with 3 number inputs, AJAX-saved via `PATCH /settings/dashboard-widgets`
- Counter badges show the combined total (today + upcoming)

### Consequences

- New migration adds 3 columns to `users` table — lightweight, no data backfill needed
- Existing behavior is unchanged when columns are null (default)
- Settings endpoint follows the same AJAX pattern as timezone and prune settings
- 16 new tests added (dashboard + settings), all 1355 tests passing

### Follow-ups / open questions

- None — feature is self-contained
