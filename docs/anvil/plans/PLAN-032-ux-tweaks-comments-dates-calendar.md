# PLAN-032: UX Tweaks — Comment Ordering, Optional Dates, Monday-First Calendar

**Created:** 2026-04-21
**Status:** In Progress
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

Four independent UX improvements requested together: (1) the activity feed (comment system) is locked to chronological order with no user control; (2) Tasks, Follow-Ups, and Meetings currently force a date, blocking users from preparing items before a date is known; (3) the calendar view starts on Sunday and does not visually distinguish weekends, which feels wrong for a Dutch/European user; (4) the dashboard's "upcoming calendar items" widget is hard-limited to 3 items, which hides entire upcoming days when a single day already contains 3+ events.

**Note on terminology:** Mithril has no dedicated `Comment` model. The user's "comment system" refers to the **Activity Feed** (`Activity` model + `HasActivityFeed` trait), which renders messages/activity entries on resources. This plan targets the activity feed.

## Acceptance Criteria

1. Users can toggle the activity feed between "oldest first" and "latest first"; the choice persists across sessions.
2. Creating or editing a Meeting without `scheduled_at` succeeds (matching existing behavior for Task `deadline` and FollowUp `follow_up_date`).
3. All list/filter/sort queries for Tasks, FollowUps, and Meetings gracefully handle records without dates (no errors, nulls grouped sensibly).
4. The calendar view's 7-day grid starts on Monday and ends on Sunday.
5. Saturday and Sunday cells in the calendar are visually distinguishable from weekdays via a distinct background color in both light and dark modes.
6. The dashboard upcoming-calendar widget shows all events within its query window (no hard 3-item cap), while still hiding events that have already ended.
7. No regressions: existing tests pass, `npx tsc --noEmit` clean, `npm run build` succeeds.

## Technical Design

### Approach

**Feature 1 — Activity feed ordering:** Add a single nullable string column `activity_sort_order` to the `users` table (values: `asc` | `desc`, default `asc`). Inject the preference into `HasActivityFeed::getActivityFeed()` so both `Activity::scopeChronological()` and `Activity::scopeLatestFirst()` are selectable. Expose a toggle control in `<x-tl.activity-feed>` that auto-saves via the existing `AutoSaveController` against the authenticated user, then refreshes the feed partial.

**Feature 2 — Optional dates:** Only Meetings require a schema change (`scheduled_at` must become nullable). Tasks and FollowUps already have nullable date columns and `nullable` validation; the fixes there are query-level null-safety in page controllers and model scopes. UI forms must also tolerate empty date inputs.

**Feature 3 — Monday-first calendar with weekend styling:** Replace the hardcoded `for ($i = 0; $i < 7; $i++)` loop in `resources/views/components/tl/calendar-events.blade.php` with a loop seeded from `Carbon::now()->startOfWeek(Carbon::MONDAY)`. Add conditional Tailwind classes on day cells using `$day->isWeekend()`.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `database/migrations/` (new) | Create | Add `activity_sort_order` column to `users` |
| `database/migrations/` (new) | Create | Make `meetings.scheduled_at` nullable |
| `app/Models/User.php` | Modify | Add `activity_sort_order` to fillable/casts |
| `app/Models/Traits/HasActivityFeed.php` | Modify | Accept sort order; apply correct scope |
| `app/Http/Controllers/Web/PartialController.php` | Modify | Pass user preference into `getActivityFeed()` |
| `resources/views/components/tl/activity-feed.blade.php` | Modify | Add sort toggle UI + auto-save binding |
| `app/Http/Requests/MeetingRequest.php` | Modify | Change `scheduled_at` from `required` to `nullable` |
| `app/Http/Controllers/Web/MeetingPageController.php` | Modify | Null-safe ordering/filtering of meetings |
| `app/Http/Controllers/Web/FollowUpPageController.php` | Modify | Null-safe scopes for undated follow-ups |
| `app/Models/FollowUp.php` | Modify | Guard scopes (`overdue`, `dueToday`, `dueThisWeek`, `upcoming`) with `whereNotNull` |
| `app/Models/Meeting.php` | Modify | Add "undated" scope / guard existing date scopes |
| `resources/views/components/tl/meeting-card.blade.php` or equivalent | Modify | Display undated meetings gracefully |
| `resources/views/components/tl/calendar-events.blade.php` | Modify | Monday-first loop + weekend styling |
| `app/Http/Controllers/Web/PartialController.php` | Modify | Remove 3-item cap from `dashboardCalendar()` |
| `resources/views/components/tl/calendar-upcoming.blade.php` | Modify | Ensure ended-today events are not rendered |

### Edge Cases

- Activity feed: user record predating migration has `null` sort order → default to `asc` (current behavior).
- Meetings without `scheduled_at` should not appear in "upcoming" or "this week" buckets; need a separate "undated/prep" grouping or footer list.
- Follow-ups without `follow_up_date` should surface somewhere (prep list), else users cannot find what they prepared.
- Calendar week boundaries: at year/month rollover, the Monday-seeded week may span two months; date formatting must still render correctly.
- Dark mode: weekend color must be legible against `dark:bg-gray-900`.
- Upcoming widget: the existing `notEndedAt(now())` query filter already hides fully-past events; verify it also hides same-day events where `end_at` is before `now()` (e.g., a 10:00–11:00 meeting viewed at 14:00). If the Blade-side `$isHappening()` closure still leaks ended items, tighten the server-side filter.

## Implementation Phases

### Phase 1: Schema & validation updates
- **Goal:** Database and request-validation foundation for features 1 and 2.
- **Model:** haiku
- **Specs:**
  - [x] Migration adds `activity_sort_order` string column to `users` (nullable, default `'asc'`).
  - [x] Migration makes `meetings.scheduled_at` nullable and reversible.
  - [x] `User` model exposes `activity_sort_order` in `$fillable`.
  - [x] `MeetingRequest` accepts a null `scheduled_at` via `nullable` rule.
  - [x] Migrations run cleanly on MariaDB and SQLite (test DB).
- **Files:** `database/migrations/`, `app/Models/User.php`, `app/Http/Requests/MeetingRequest.php`

### Phase 2: Activity feed sort toggle
- **Goal:** Users can flip activity feed ordering; preference persists.
- **Model:** sonnet
- **Specs:**
  - [x] `HasActivityFeed::getActivityFeed()` accepts an optional sort direction; defaults to user's `activity_sort_order` or `asc`.
  - [x] Applying `desc` returns activities newest-first; `asc` returns oldest-first.
  - [x] `PartialController::activityFeed()` passes the authenticated user's preference.
  - [x] `<x-tl.activity-feed>` renders an asc/desc toggle wired to `AutoSaveController` for the `users.activity_sort_order` field.
  - [x] Toggling refreshes the feed without a full page reload.
  - [x] Feature tests cover both sort directions and preference persistence.
- **Files:** `app/Models/Traits/HasActivityFeed.php`, `app/Http/Controllers/Web/PartialController.php`, `resources/views/components/tl/activity-feed.blade.php`, `resources/js/components/activity-feed.ts`, `tests/Feature/`

### Phase 3: Undated tasks, follow-ups, meetings
- **Goal:** All three entity types can exist without a date; list views handle nulls.
- **Model:** sonnet
- **Specs:**
  - [x] Creating a Meeting without `scheduled_at` succeeds via API and form.
  - [x] `FollowUp` scopes (`overdue`, `dueToday`, `dueThisWeek`, `upcoming`) exclude null-dated records.
  - [x] `Meeting` date-scoped queries in `MeetingPageController` exclude null-dated records; null-dated meetings appear in a dedicated "Undated / Prep" grouping.
  - [x] `FollowUp` undated records appear in a "Prep" grouping on the follow-ups page.
  - [x] Existing `Task::scopeOverdue()` already null-safe; add a regression test.
  - [x] Meeting/FollowUp cards render "No date set" (or equivalent) when date is null.
- **Files:** `app/Http/Controllers/Web/MeetingPageController.php`, `app/Http/Controllers/Web/FollowUpPageController.php`, `app/Models/FollowUp.php`, `app/Models/Meeting.php`, `resources/views/pages/meetings/`, `resources/views/pages/follow-ups/`, `resources/views/components/tl/`, `tests/Feature/`, `tests/Unit/Models/`

### Phase 4: Monday-first calendar + weekend styling
- **Goal:** Calendar renders Mon to Sun with distinct weekend cells.
- **Model:** haiku
- **Specs:**
  - [x] 7-day loop seeds from the Monday of the current week (`Carbon::now()->startOfWeek(Carbon::MONDAY)`).
  - [x] Day order is strictly Mon, Tue, Wed, Thu, Fri, Sat, Sun.
  - [x] Saturday and Sunday cells carry a distinct background class in light mode.
  - [x] Saturday and Sunday cells carry a legible distinct background class in dark mode.
  - [x] Weekday cells retain existing styling.
  - [x] Event rendering inside each day is unchanged.
- **Files:** `resources/views/components/tl/calendar-events.blade.php`

### Phase 5: Upcoming calendar widget — remove 3-item cap
- **Goal:** Dashboard upcoming widget shows all events in its query window; events ended earlier today are hidden.
- **Model:** haiku
- **Specs:**
  - [x] `PartialController::dashboardCalendar()` no longer calls `->limit(3)`.
  - [x] Query remains bounded by the end-of-week window (`until(now()->endOfWeek())`).
  - [x] Events with `end_at < now()` (including same-day past events) are not rendered.
  - [x] A day with 3+ events does not prevent the following day's events from appearing.
  - [x] Widget still groups events by day label ("Today", "Tomorrow", dated).
  - [x] Feature test: seed 4 events today and 2 events tomorrow; assert all 6 render (when none are past).
  - [x] Feature test: seed a past-today event; assert it is excluded from the widget output.
- **Files:** `app/Http/Controllers/Web/PartialController.php`, `app/Http/Controllers/Web/DashboardController.php`, `resources/views/components/tl/calendar-upcoming.blade.php`, `tests/Feature/Pages/DashboardCalendarWidgetTest.php`, `tests/Feature/Pages/DashboardCalendarTest.php`

### Phase 6: Upcoming widget — respect user limit, carve out all-day, add leaf separator

Added as an amendment after Phase 5 review. Refines the upcoming-calendar widget per user feedback.

- **Goal:** Re-apply the user's configured upcoming-items limit (`users.dashboard_upcoming_meetings`, default 5), while exempting all-day events from the limit, and visually separate today from later days with a leaf divider.
- **Model:** sonnet
- **Specs:**
  - [x] `DashboardController::index()` and `PartialController::dashboardCalendar()` apply the authenticated user's `dashboard_upcoming_meetings` limit (fall back to 5 when null) to **timed** calendar events only.
  - [x] All-day events (`is_all_day = true`) are always included regardless of the limit; they do not consume a slot.
  - [x] Within each day group, all-day events render at the top of the group, before timed events.
  - [x] All-day events still respect the end-of-week window and the `notEndedAt(now())` filter.
  - [x] A single elvish-leaf divider renders once between today's day group and the first subsequent day group. No divider appears when today has no visible events.
  - [x] The divider uses the existing `elvish-divider-leaf` utility (see `resources/css/app.css`).
  - [x] Feature test: user with `dashboard_upcoming_meetings = 3`, seed 5 timed today + 2 all-day today + 4 timed tomorrow; assert 3 timed today + 2 all-day today + (remaining slots filled from tomorrow) render correctly.
  - [x] Feature test: all-day events never dropped due to limit.
  - [x] Feature test: leaf divider appears exactly once between today and the first non-today day.
- **Files:** `app/Http/Controllers/Web/PartialController.php`, `app/Http/Controllers/Web/DashboardController.php`, `app/Models/CalendarEvent.php`, `resources/views/components/tl/calendar-upcoming.blade.php`, `tests/Feature/Pages/DashboardCalendarWidgetTest.php`

### Phase 7: Flatpickr theming and locale

Added as an amendment after Phase 6. The Blade calendar view was fixed in Phase 4, but the Flatpickr date-picker widget used throughout the app (task deadlines, follow-up dates, meeting dates, etc.) still opens Sunday-first, has no weekend distinction, and the month dropdown is unstyled against the dark theme.

- **Goal:** Flatpickr instances globally start the week on Monday, visually distinguish Saturday/Sunday columns with warm-stone backgrounds (matching Phase 4), and style the month/year dropdown to match the dark theme.
- **Model:** sonnet
- **Specs:**
  - [x] Both `resources/js/components/date-picker.ts` and `resources/js/components/auto-save-date-picker.ts` pass `weekStart: 1` (or `locale: { firstDayOfWeek: 1 }`) to the Flatpickr constructor so the week starts on Monday.
  - [x] A shared Flatpickr default is extracted to avoid duplication; both components consume it.
  - [x] Custom CSS added to `resources/css/app.css` (Tailwind v4 `@layer components` or equivalent) targets the weekend weekday headers and day cells with `bg-stone-100 dark:bg-stone-900/40` (matching the Phase 4 palette).
  - [x] The Flatpickr month `<select>` (`.flatpickr-monthDropdown-months`) and its options render with dark theme-compatible background, text, and border colors in dark mode; light mode is unaffected.
  - [x] The year input `.numInputWrapper` and its arrows also render legibly in dark mode.
  - [x] `npm run build` succeeds; `npx tsc --noEmit` clean; existing tests pass.
  - [x] Manual verification in both light and dark mode: open any date picker, confirm week starts Monday, weekend columns are tinted, dropdown is legible.
- **Files:** `resources/js/components/date-picker.ts`, `resources/js/components/auto-save-date-picker.ts`, `resources/css/app.css`

## Parallelization

**Strategy:** Partial parallel
**Execution method:** Subagents

Phase 1 is the blocker; Phases 2, 3, 4, and 5 can run in parallel after it completes. Phases 2 and 5 both touch `PartialController.php` but edit different methods (`activityFeed()` vs `dashboardCalendar()`); subagents should be instructed to preserve the other method's current shape. If conflicts are a concern, run Phase 5 sequentially after Phase 2.

- Phase 2 owns `HasActivityFeed`, `PartialController::activityFeed()`, activity-feed view/JS.
- Phase 3 owns follow-up/meeting controllers, models, views.
- Phase 4 owns only `calendar-events.blade.php`.
- Phase 5 owns `PartialController::dashboardCalendar()` and `calendar-upcoming.blade.php`.

Subagents are appropriate; no coordination or shared state during execution.

## Review

**Review strategy:** end

Review scope is small, per-file changes are isolated, and no phase depends on another's mid-execution state. One Reviewer pass over the full diff is efficient. Upgrade to per-phase only if Phase 2's AutoSave wiring proves tricky. Phase 2 and Phase 5 both touch `PartialController.php`; the end review will confirm no regressions across methods.

## Out of Scope

- Introducing a dedicated `Comment` model (not needed; activity feed covers the use case).
- Making week-start user-configurable (hardcoded Monday is sufficient per request).
- Timezone handling beyond what is already in place.
- Undated-item bulk operations or reminders.
- Changing the Flatpickr locale or any other date-picker configuration.

## Open Questions

_(Resolved during plan review.)_

1. **Undated Meetings/FollowUps placement:** Collapsed section at the bottom of the list.
2. **Weekend background color:** Warm stone — `bg-stone-100` in light mode, `dark:bg-stone-900/40` in dark mode.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
| 2026-04-21 | 6 (new) | Added Phase 6: respect `dashboard_upcoming_meetings` limit on timed events, exempt all-day events, add leaf separator between today and later days | User feedback during Phase 5 review: hard-removing the limit was too coarse; user's configured limit should still apply, all-day items should always be visible, and a visual separator aids scanning | — |
| 2026-04-21 | 7 (new) | Added Phase 7: Flatpickr Monday-first, weekend styling, dark-mode month/year dropdown | User screenshot showed the Flatpickr datepicker still Sunday-first, no weekend distinction, and unstyled month dropdown in dark theme. Phase 4 only fixed the Blade calendar, not the JS picker. | — |
