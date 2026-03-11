# Recurring Tasks — Implementation Plan

## Summary

Allow users to make any task recurring by adding a recurrence schedule. When a recurring task is marked as "Done", the system automatically creates a new copy of that task with the status reset to "Open" and the deadline advanced to the next occurrence according to the schedule. The original completed task is preserved in history. Recurrence is configured per-task via a simple schedule definition (daily, weekly, biweekly, monthly, or custom interval).

---

## Design Decisions

### Recurrence Lives on the Task

Recurrence is a set of fields on the `tasks` table, not a separate entity. This keeps the system simple — a recurring task is just a task with extra fields. No separate "template" or "series" model needed for v1.

### Copy-on-Complete, Not Reopen

When a recurring task is completed, a **new task** is created rather than reopening the original. This preserves the completed task in history (visible in analytics, weekly reflections, and as a record of work done). The new task is a standalone copy — editing it does not affect completed instances.

### Series Tracking

Each recurring task carries a `recurrence_series_id` (UUID) that links all instances in the same series. This allows future features like "view series history" or "stop all future occurrences" without requiring a separate model. The first task in a series generates the UUID; all copies inherit it.

### Deadline Calculation

The next deadline is calculated from the **current task's deadline**, not from the completion date. This prevents schedule drift. Example: a weekly task due Monday will always create the next instance for next Monday, regardless of whether it's completed on Monday or Thursday.

If the task has **no deadline**, the next deadline is calculated from the completion date instead (since there's no reference date to anchor to).

### Skipping Past Dates

If a task is completed late (e.g., a weekly task due Monday completed the following Wednesday), the next instance is scheduled for the **next future occurrence**, not for a date that has already passed. This prevents a backlog of overdue tasks from appearing instantly.

---

## Data Model

### Modified Table: `tasks`

New columns via migration:

```
tasks (existing, add columns)
├── is_recurring              BOOLEAN, NOT NULL, DEFAULT FALSE
├── recurrence_interval       VARCHAR(20), NULL  — 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom'
├── recurrence_custom_days    SMALLINT UNSIGNED, NULL  — Only used when interval = 'custom', number of days
├── recurrence_series_id      CHAR(36), NULL  — UUID linking all instances in a series
├── recurrence_parent_id      BIGINT UNSIGNED, NULL, FK → tasks.id, ON DELETE SET NULL
```

### Enum: `RecurrenceInterval`

```php
enum RecurrenceInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Custom = 'custom';
}
```

### Index

```sql
INDEX idx_tasks_recurrence_series (recurrence_series_id)
```

---

## Recurrence Logic

### Next Deadline Calculation

| Interval | Calculation |
|----------|-------------|
| `daily` | `+1 day` |
| `weekly` | `+7 days` |
| `biweekly` | `+14 days` |
| `monthly` | `+1 month` (same day-of-month, clamped to month end) |
| `custom` | `+N days` (where N = `recurrence_custom_days`) |

### Skip-Past Logic

After calculating the raw next date, if it is in the past (before today), advance by intervals until it lands on today or later:

```php
while ($nextDeadline->isPast()) {
    $nextDeadline = $this->advanceByInterval($nextDeadline, $interval, $customDays);
}
```

### Fields Copied to New Instance

| Field | Behavior |
|-------|----------|
| `title` | Copied |
| `description` | Copied |
| `priority` | Copied |
| `category` | Copied |
| `team_id` | Copied |
| `team_member_id` | Copied |
| `task_group_id` | Copied |
| `task_category_id` | Copied |
| `is_private` | Copied |
| `is_recurring` | Copied (TRUE) |
| `recurrence_interval` | Copied |
| `recurrence_custom_days` | Copied |
| `recurrence_series_id` | Copied (same UUID) |
| `recurrence_parent_id` | Set to the completed task's ID |
| `status` | Reset to `Open` |
| `deadline` | Calculated next occurrence |
| `sort_order` | Auto-assigned (via `HasSortOrder`) |
| `user_id` | Auto-assigned (via `BelongsToUser`) |

**Not copied:** `id`, `created_at`, `updated_at`, `sort_order`, follow-ups, calendar event links.

---

## Backend Architecture

### Service: `RecurrenceService`

```php
class RecurrenceService
{
    /**
     * Calculate the next deadline based on the current deadline and recurrence interval.
     * Skips past dates to always return a future or today date.
     */
    public function calculateNextDeadline(
        ?CarbonInterface $currentDeadline,
        RecurrenceInterval $interval,
        ?int $customDays = null,
    ): Carbon

    /**
     * Create the next occurrence of a recurring task.
     * Returns the newly created task.
     */
    public function createNextOccurrence(Task $completedTask): Task

    /**
     * Check whether a task should spawn a next occurrence.
     * Returns true if the task is recurring and was just marked as Done.
     */
    public function shouldRecur(Task $task, TaskStatus $oldStatus, TaskStatus $newStatus): bool

    /**
     * Stop recurrence for a task (and optionally all future instances).
     * Sets is_recurring = false.
     */
    public function stopRecurrence(Task $task): void
}
```

### Event/Listener Integration

The existing `TaskStatusChanged` event already fires when a task status changes. Add a new listener:

```php
class CreateRecurringTaskOccurrence
{
    public function handle(TaskStatusChanged $event): void
    {
        if (! $this->recurrenceService->shouldRecur($event->task, $event->oldStatus, $event->newStatus)) {
            return;
        }

        $this->recurrenceService->createNextOccurrence($event->task);
    }
}
```

Register in `EventServiceProvider`:
```php
TaskStatusChanged::class => [
    CreateFollowUpOnWaiting::class,  // existing
    CreateRecurringTaskOccurrence::class,  // new
],
```

### Model Updates

```php
// Task model — add:
protected $fillable = [
    // ...existing...
    'is_recurring',
    'recurrence_interval',
    'recurrence_custom_days',
    'recurrence_series_id',
    'recurrence_parent_id',
];

protected function casts(): array
{
    return [
        // ...existing...
        'is_recurring' => 'boolean',
        'recurrence_interval' => RecurrenceInterval::class,
    ];
}

/**
 * Get the previous instance in this recurrence series.
 */
public function recurrenceParent(): BelongsTo

/**
 * Get the next instance spawned from this task.
 */
public function recurrenceChild(): HasOne

/**
 * Get all tasks in the same recurrence series.
 */
public function seriesTasks(): HasMany  // via recurrence_series_id
```

### AutoSaveController Integration

The recurrence fields (`is_recurring`, `recurrence_interval`, `recurrence_custom_days`) are added to `$fillable` so they work with the existing `AutoSaveController` — no special handling needed.

When `is_recurring` is toggled ON via auto-save and `recurrence_series_id` is null, the controller (or a model observer) generates a new UUID.

### Filterable Extension

Add recurrence filter to the Task model's `$filterableFields`:

```php
'is_recurring' => 'boolean',
```

---

## API Endpoints

No new controllers or routes needed. Everything works through existing infrastructure:

| Action | Mechanism |
|--------|-----------|
| Toggle recurrence on/off | `AutoSaveController` → `is_recurring` field |
| Set interval | `AutoSaveController` → `recurrence_interval` field |
| Set custom days | `AutoSaveController` → `recurrence_custom_days` field |
| Stop recurrence | `AutoSaveController` → `is_recurring = false` |
| Next occurrence created | Automatic via `TaskStatusChanged` event listener |
| Filter recurring tasks | Existing `FilterManager` → `is_recurring` filter |

### New API endpoint (optional, for series history):

```php
// GET /api/tasks/{task}/series
// Returns all tasks sharing the same recurrence_series_id, ordered by creation date.
// Only meaningful if the task has a recurrence_series_id.
```

---

## Frontend

### Task Card — Recurrence Indicator

Recurring tasks display a small recurrence icon (circular arrows) next to the title or deadline. Hovering shows the interval (e.g., "Repeats weekly").

### Task Detail/Edit — Recurrence Settings

Add a recurrence section to the task edit view (or inline on the task card):

```
Recurring: [toggle: off/on]
├── Interval: [select: Daily / Weekly / Biweekly / Monthly / Custom]
│   └── Custom: every [input: number] days
└── Series info (if part of a series):
    └── "Part of a recurring series" — [link: View series history]
```

All fields auto-save via existing `autoSaveField` Alpine component.

### Alpine Component: `recurrenceSettings`

```typescript
interface RecurrenceSettingsData {
    taskId: number;
    isRecurring: boolean;
    interval: string | null;
    customDays: number | null;

    toggleRecurrence(): void;  // Saves is_recurring, auto-generates series UUID
    updateInterval(interval: string): void;  // Saves recurrence_interval
    updateCustomDays(days: number): void;  // Saves recurrence_custom_days
}
```

### Notification on Completion

When a recurring task is marked as Done, show a brief toast notification:

```
"Task completed. Next occurrence created for [date]."
```

This is informational — no user action needed.

### TypeScript Types

```typescript
// resources/js/types/models.ts — update Task:
interface Task {
    // ...existing fields...
    is_recurring: boolean;
    recurrence_interval: RecurrenceInterval | null;
    recurrence_custom_days: number | null;
    recurrence_series_id: string | null;
    recurrence_parent_id: number | null;
}

type RecurrenceInterval = 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom';
```

---

## Implementation Phases

### Phase 1: Data Layer (backend agent)

**Files:**
- `database/migrations/xxxx_add_recurrence_fields_to_tasks_table.php`
- `app/Enums/RecurrenceInterval.php`
- `app/Models/Task.php` (update: fillable, casts, relationships, filterableFields)

**Tests (TDD — write first):**
- Migration adds all columns with correct defaults
- `RecurrenceInterval` enum has all expected cases
- Task model casts `recurrence_interval` to enum
- Task `recurrenceParent()`, `recurrenceChild()`, `seriesTasks()` relationships work
- `is_recurring` filter works via `Filterable` trait

**Depends on:** nothing

### Phase 2: Recurrence Service (backend agent)

**Files:**
- `app/Services/RecurrenceService.php`

**Tests (TDD — write first):**
- `calculateNextDeadline()`: daily → +1 day
- `calculateNextDeadline()`: weekly → +7 days
- `calculateNextDeadline()`: biweekly → +14 days
- `calculateNextDeadline()`: monthly → +1 month (test month-end clamping: Jan 31 → Feb 28)
- `calculateNextDeadline()`: custom → +N days
- `calculateNextDeadline()`: skips past dates to land on today or future
- `calculateNextDeadline()`: null deadline uses today as base
- `shouldRecur()`: returns true only when task is recurring AND status changed to Done
- `shouldRecur()`: returns false if already Done → Done
- `shouldRecur()`: returns false if `is_recurring` is false
- `createNextOccurrence()`: creates new task with correct field copies
- `createNextOccurrence()`: new task has status Open
- `createNextOccurrence()`: new task has calculated next deadline
- `createNextOccurrence()`: new task preserves `recurrence_series_id`
- `createNextOccurrence()`: new task sets `recurrence_parent_id` to completed task's ID
- `createNextOccurrence()`: does NOT copy follow-ups or calendar event links
- `stopRecurrence()`: sets `is_recurring` to false

**Depends on:** Phase 1

### Phase 3: Event Listener (backend agent)

**Files:**
- `app/Listeners/CreateRecurringTaskOccurrence.php`
- `app/Providers/EventServiceProvider.php` (update: register listener)

**Tests (TDD — write first):**
- Listener creates next occurrence when recurring task marked Done
- Listener does nothing when non-recurring task marked Done
- Listener does nothing when recurring task changes to non-Done status
- Listener does nothing when task was already Done (Done → Done edge case)
- Integration: full flow — create recurring task, mark Done, verify new task exists with correct data

**Depends on:** Phase 2

### Phase 4: Series UUID Auto-Generation (backend agent)

**Files:**
- `app/Models/Task.php` (update: boot method or observer)

**Tests (TDD — write first):**
- Setting `is_recurring = true` on a task without `recurrence_series_id` generates a UUID
- Setting `is_recurring = true` on a task that already has a `recurrence_series_id` keeps existing UUID
- Setting `is_recurring = false` does NOT clear `recurrence_series_id` (history preserved)

**Depends on:** Phase 1

### Phase 5: Frontend UI (frontend + typescript agent)

**Files:**
- `resources/views/components/tl/recurrence-settings.blade.php` (new)
- `resources/js/components/recurrenceSettings.ts` (new)
- `resources/js/app.ts` (update: register component)
- `resources/js/types/models.ts` (update: Task interface)
- Task card Blade template (update: recurrence indicator icon)

**Behavior:**
- Toggle on/off via auto-save
- Interval selector appears when recurring is on
- Custom days input appears when interval is "custom"
- Recurrence icon on task cards
- Toast notification when recurring task completed

**Depends on:** Phase 3

### Phase 6: Series History (optional, backend + frontend)

**Files:**
- `app/Http/Controllers/Api/TaskController.php` (update: add `series` method)
- `routes/api.php` (update: add series route)
- Frontend: series history view (simple list of completed instances)

**Depends on:** Phase 5

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 1 | backend | Migration, enum, model updates |
| 2 | backend | RecurrenceService |
| 3 | backend | Listener, EventServiceProvider |
| 4 | backend | Model boot/observer |
| 5 | frontend + typescript | Blade component, Alpine component, types |
| 6 | backend + frontend | API endpoint, series view |

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Task marked Done but `recurrence_interval` is null | Do not recur. `shouldRecur()` requires both `is_recurring = true` AND a valid interval. |
| Custom interval with `recurrence_custom_days = 0` | Validation prevents this. Minimum is 1 day. |
| Monthly recurrence on Jan 31 | Carbon's `addMonth()` handles this: Jan 31 → Feb 28 (or 29 in leap year). |
| Task completed multiple statuses (Open → Done → Open → Done) | Each Done transition spawns a new occurrence. The second occurrence is a child of the reopened task, not of the first child. |
| User disables recurrence after task is Done | No effect — the next occurrence was already created at the moment of completion. The new task has `is_recurring = true` (copied), so the user must disable recurrence on the new task too if they want to stop the series. |
| Recurring task deleted | Children are not deleted (independent tasks). `recurrence_parent_id` is SET NULL via FK. |
| Bulk status change to Done | Each task fires `TaskStatusChanged` individually, each spawns its own occurrence. |

---

## Out of Scope (Potential Future Enhancements)

- **Day-of-week scheduling** — e.g., "every Monday and Wednesday" (requires a more complex schedule model)
- **End date / max occurrences** — stop recurring after N times or after a date
- **Recurring follow-ups** — same pattern but for the FollowUp model
- **Series-wide edits** — "update all future occurrences" (requires template model)
- **Calendar integration** — create calendar events for recurring deadlines
