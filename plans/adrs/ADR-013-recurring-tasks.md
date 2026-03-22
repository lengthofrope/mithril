## ADR-013: Recurring tasks via copy-on-complete with model observer event dispatch

**Date:** 2026-03-11
**Phase:** Recurring Tasks
**Tags:** backend, frontend, tasks, recurrence, observer, events
**Status:** Accepted

### Context

Users needed the ability to make tasks recurring so that completing a task automatically creates the next occurrence. The plan specified recurrence fields on the `tasks` table (no separate template model) with a copy-on-complete strategy triggered by the `TaskStatusChanged` event.

During implementation, a critical gap was discovered: `TaskStatusChanged` was only fired from explicitly coded dispatch calls, but `AutoSaveController`, `TaskPageController::bulkUpdate()`, and `TaskPageController::move()` all update task status via `$task->update()` without dispatching the event. This meant neither the existing `CreateFollowUpOnWaiting` listener nor the new recurrence listener would fire for these code paths.

Alternatives considered for the event gap:
- **Add manual dispatch to each controller method** — fragile, easy to forget in future code paths.
- **Model observer** — catches all status changes regardless of code path. Single source of truth.

### Decision

1. **TaskObserver** (`app/Observers/TaskObserver.php`) registered in `AppServiceProvider::boot()` that dispatches `TaskStatusChanged` automatically whenever the `status` column is dirty on update. This replaces the need for manual event dispatch in controllers and ensures all code paths (API, auto-save, bulk update, kanban move) trigger listeners consistently.

2. **Copy-on-complete strategy** — when a recurring task transitions to `Done`, the `CreateRecurringTaskOccurrence` listener (via `RecurrenceService`) creates a new task with status `Open` and the deadline advanced to the next occurrence. The completed task is preserved as history.

3. **Series tracking** — a `recurrence_series_id` (UUID) links all instances. Auto-generated via a `saving` hook on the Task model when `is_recurring` is first set to `true`.

4. **Deadline calculation** — next deadline is computed from the current task's deadline (not completion date) to prevent schedule drift. If the calculated date is in the past, it advances by intervals until landing on today or a future date. Tasks with no deadline use today as the base.

5. **Recurrence fields live on the `tasks` table** — `is_recurring`, `recurrence_interval`, `recurrence_custom_days`, `recurrence_series_id`, `recurrence_parent_id`. No separate model needed for v1.

6. **Frontend** — recurrence settings on the task detail page use nested `autoSaveField` Alpine components. Task cards show a recurrence indicator icon. All fields auto-save via the existing `AutoSaveController`.

### Deviation from plan

The plan referenced registering the listener in `EventServiceProvider`. This project wires events via `Event::listen()` in `AppServiceProvider::boot()` — the implementation follows the existing pattern instead.

The plan did not account for the event dispatch gap in `AutoSaveController`, `bulkUpdate()`, and `move()`. The `TaskObserver` was added as Phase 0 to fix this systemically before implementing recurrence.

### Consequences

- **Migration required** — adds 5 columns to `tasks` table with a foreign key and index.
- **TaskObserver registered** — all `Task::update()` calls that change `status` now fire `TaskStatusChanged`. This also fixes the pre-existing gap for `CreateFollowUpOnWaiting`.
- **No new routes or controllers** — everything works through existing `AutoSaveController` and event listeners.
- **Series history endpoint** deferred to Phase 6 (optional, not yet implemented).
- **Bulk status changes** each fire their own event individually, which may create multiple recurring instances simultaneously — this is by design per the plan.
