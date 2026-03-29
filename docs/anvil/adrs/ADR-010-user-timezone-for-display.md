## ADR-010: User timezone setting for display-time conversion

**Date:** 2026-03-10
**Phase:** Implementation (Phase 1-3)
**Tags:** backend, frontend, settings, calendar, timezone
**Status:** Accepted

### Context

Calendar events synced from Microsoft Graph are stored in UTC (the Graph API is explicitly requested to return UTC via `Prefer: outlook.timezone="UTC"`). However, the dashboard displayed times without timezone conversion, causing all events to appear one hour early for users in CET (UTC+1).

Additionally, the greeting ("Good morning/afternoon/evening") and "today" date boundaries for tasks, follow-ups, and bilas were computed in UTC rather than the user's local time.

Alternatives considered:
- **Browser-side conversion via JavaScript** — would require converting all server-rendered times client-side, inconsistent with the Blade-first architecture.
- **Auto-detect from browser `Intl.DateTimeFormat`** — fragile, no persistence, different on each device.
- **Per-user timezone stored on the user model** — simple, explicit, persisted, server-side conversion at display time.

### Decision

A `timezone` column (nullable, varchar 64) is added to the `users` table. When null, it defaults to `Europe/Amsterdam` via `User::getEffectiveTimezone()`.

**Storage stays UTC.** All database timestamps remain in UTC. Conversion happens at display time only:

1. **Calendar component** — `start_at` and `end_at` are converted via `->timezone($tz)` before formatting and day-grouping.
2. **Dashboard greeting** — `now($timezone)` determines the local hour.
3. **Dashboard "today" boundaries** — tasks due today, bilas today, and calendar date range are computed using `now($timezone)`.
4. **Settings page** — a timezone `<select>` auto-saves via AJAX (`PATCH /settings/timezone`) using `timezone_identifiers_list()` as the source of valid values. Laravel's `timezone:all` validation rule enforces correctness.

### Consequences

- **Migration:** `add_timezone_to_users_table` adds nullable `timezone` column. Existing users default to `Europe/Amsterdam`.
- **User model:** `timezone` added to `$fillable`; new `getEffectiveTimezone()` helper.
- **DashboardController:** `resolveGreeting()` and `buildTodaySection()` now accept a `$timezone` parameter.
- **Calendar-events component:** accepts a `timezone` prop for display-time conversion.
- **No impact on sync jobs** — `SyncCalendarEventsJob` and `SyncMemberAvailabilityJob` continue to operate in UTC.
- **Bugfix included:** `isKnownMicrosoftUser()` time window changed from 1 minute to 60 minutes to satisfy the Graph API's `availabilityViewInterval` minimum constraint.

### Follow-ups / open questions

- Other date displays in the app (follow-up dates, bila scheduled dates, task deadlines) are date-only fields and not affected by timezone. If timestamp-level precision is added later, those will need the same treatment.
