# Bugfixes & Improvements — Tasks, Dashboard, Kanban

**Created:** 2026-03-18
**Status:** Draft
**Author:** Bas de Kort

## Problem Statement

Six usability issues reported: (1) the overdue tasks filter shows all tasks instead of only overdue ones, (2) dashboard task widgets don't sort by priority within each day, (3) no way to filter on recurring tasks, (4) kanban cards can only be dragged to the top of the next column when scrolled down, (5) task group can't be changed without opening the task, and (6) task search is completely broken — does nothing in list view, breaks layout in kanban view.

## Acceptance Criteria

1. Task list and kanban filter bars include an "Overdue" filter that shows only tasks with a deadline before today and status ≠ done
2. Dashboard task widgets (today + upcoming) sort by deadline first, then by priority (urgent → high → normal → low) within the same day
3. Task list and kanban filter bars include a "Recurring" boolean filter
4. Kanban cards can be dragged across columns regardless of vertical scroll position
5. Kanban drag-and-drop uses the existing `.drag-handle` element instead of the full card
6. Task group can be changed inline on the task card via the existing `inline-select-pill` component
7. Task search works correctly in both list and kanban views, returning filtered results without breaking layout
8. All existing tests continue to pass; new behavior is covered by tests

## Technical Design

### Approach

All six issues are isolated fixes in different parts of the codebase with no interdependencies. Each can be implemented and tested independently.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `app/Http/Controllers/Web/TaskPageController.php` | Modify | Add `overdue`, `is_recurring`, `search` to filter extraction; apply overdue scope; apply search scope; add AJAX check to `kanban()` |
| `app/Models/Task.php` | Modify | Add `scopeOverdue()` scope |
| `resources/views/pages/tasks/index.blade.php` | Modify | Add overdue + recurring filter definitions to filter bar |
| `resources/views/pages/tasks/kanban.blade.php` | Modify | Add overdue + recurring filter definitions to filter bar; ensure column overflow styling |
| `app/Http/Controllers/Web/DashboardController.php` | Modify | Add secondary `orderByRaw(CASE...)` for priority after `orderBy('deadline')` |
| `app/Http/Controllers/Web/PartialController.php` | Modify | Same priority sort fix for polling endpoint |
| `resources/js/components/sortable-kanban.ts` | Modify | Add `scroll`, `scrollSensitivity`, `scrollSpeed`, `forceFallback`, `handle` options |
| `resources/views/components/tl/task-card.blade.php` | Modify | Add `inline-select-pill` for task group |
| `resources/views/partials/kanban-board.blade.php` | Create | Extract kanban board markup into a partial for AJAX responses |

### Edge Cases & Error Handling

1. **Overdue filter + status filter combined** — overdue already excludes done tasks; if user also filters by status, both conditions apply (AND logic via Filterable trait)
2. **Overdue filter + deadline date-range filter** — both apply; overdue narrows to `< today`, date-range further narrows if set
3. **Tasks without deadline** — excluded from overdue results (no deadline = not overdue)
4. **Priority sort on tasks without priority** — NULL priority sorts last (after low)
5. **Kanban scroll + touch devices** — `forceFallback: true` ensures consistent behavior across browsers; `handle` prevents accidental drags on mobile
6. **Kanban columns with few items** — scroll options have no negative effect on short columns
7. **Task with no group** — inline-select-pill shows empty/placeholder state; selecting "None" sets `task_group_id` to null
8. **Task groups loaded per-card** — groups must be passed to task-card as a shared variable to avoid N+1 queries
9. **Search with empty string** — must be skipped (already handled by Searchable trait's empty check)
10. **Kanban AJAX partial** — must return only the board markup, not the full page with layout/breadcrumbs/toolbar

## Implementation Phases

### Phase 1: Fix task search (list + kanban)
- **Goal:** Task search works correctly in both views without breaking layout
- **Specs:**
  - [ ] TaskPageController `index()` extracts `search` from request and applies `->search($term)` scope when present
  - [ ] TaskPageController `kanban()` extracts `search` from request and applies `->search($term)` scope when present
  - [ ] TaskPageController `kanban()` checks `$request->ajax()` and returns a kanban board partial instead of the full page
  - [ ] Kanban board markup extracted into `resources/views/partials/kanban-board.blade.php`, included by both full page and AJAX response
  - [ ] Kanban `is_private` filter also added to `$request->only()` (currently missing)
  - [ ] Search in list view returns matching tasks correctly
  - [ ] Search in kanban view returns matching tasks in correct column layout
- **Files:** `app/Http/Controllers/Web/TaskPageController.php`, `resources/views/pages/tasks/kanban.blade.php`, `resources/views/partials/kanban-board.blade.php`

### Phase 2: Overdue tasks filter
- **Goal:** Users can filter task list and kanban to show only overdue tasks
- **Specs:**
  - [ ] Task model has a `scopeOverdue` scope: `deadline < today AND status != done`
  - [ ] TaskPageController `index()` extracts `overdue` from request and applies scope when truthy
  - [ ] TaskPageController `kanban()` extracts `overdue` from request and applies scope when truthy
  - [ ] Task list index filter bar includes an "Overdue" boolean filter (`field: overdue, type: boolean`)
  - [ ] Kanban filter bar includes an "Overdue" boolean filter
  - [ ] Tasks without a deadline are excluded from overdue results
  - [ ] Overdue filter combines correctly with other active filters
- **Files:** `app/Models/Task.php`, `app/Http/Controllers/Web/TaskPageController.php`, `resources/views/pages/tasks/index.blade.php`, `resources/views/pages/tasks/kanban.blade.php`

### Phase 3: Dashboard priority sorting
- **Goal:** Dashboard task widgets sort by priority (urgent first) within each day
- **Specs:**
  - [ ] `DashboardController::buildTodaySection()` sorts tasks by deadline ASC then priority (urgent → high → normal → low)
  - [ ] `DashboardController::buildUpcomingSection()` applies the same sort
  - [ ] `PartialController::dashboardTasks()` applies the same sort for both today and upcoming queries
  - [ ] Priority sort uses `CASE WHEN` expression to map string enum values to numeric order
  - [ ] Tasks with NULL priority sort after low
- **Files:** `app/Http/Controllers/Web/DashboardController.php`, `app/Http/Controllers/Web/PartialController.php`

### Phase 4: Recurring tasks filter
- **Goal:** Users can filter task list and kanban to show only recurring (or non-recurring) tasks
- **Specs:**
  - [ ] TaskPageController `index()` extracts `is_recurring` from request
  - [ ] TaskPageController `kanban()` extracts `is_recurring` from request
  - [ ] Task list index filter bar includes a "Recurring" boolean filter
  - [ ] Kanban filter bar includes a "Recurring" boolean filter
  - [ ] Filter correctly applies via Filterable trait (already configured as boolean type in `$filterableFields`)
- **Files:** `app/Http/Controllers/Web/TaskPageController.php`, `resources/views/pages/tasks/index.blade.php`, `resources/views/pages/tasks/kanban.blade.php`

### Phase 5: Inline task group edit
- **Goal:** Users can change a task's group directly from the task card without opening it
- **Specs:**
  - [ ] Task card component accepts a `$taskGroups` collection prop
  - [ ] Task card renders an `inline-select-pill` for `task_group_id` with group names as options
  - [ ] "None" option available to unset the group (sends null)
  - [ ] Pill uses the task's REST endpoint (`/api/v1/tasks/{id}`) with field `task_group_id`
  - [ ] Task group options are passed from the controller/view to avoid N+1 queries
  - [ ] Inline select updates immediately in the UI after save
- **Files:** `resources/views/components/tl/task-card.blade.php`, `resources/views/pages/tasks/index.blade.php`, `resources/views/pages/tasks/kanban.blade.php`, `resources/views/partials/tasks-list.blade.php`

### Phase 6: Kanban drag-and-drop scroll fix
- **Goal:** Cards can be dragged across kanban columns regardless of scroll position, using the drag handle
- **Specs:**
  - [ ] SortableJS instances use `handle: '.drag-handle'` option to restrict drag initiation to the handle element
  - [ ] SortableJS instances use `scroll: true` with `scrollSensitivity: 80` and `scrollSpeed: 12` for smoother auto-scroll
  - [ ] SortableJS instances use `forceFallback: true` for consistent cross-browser drag behavior
  - [ ] Kanban column containers have appropriate overflow/height styling for SortableJS scroll detection
  - [ ] Cards can be dragged from a scrolled-down position in one column to any position in another column
  - [ ] Drag handle cursor remains `cursor-grab` (already styled), non-handle areas of the card are not draggable
- **Files:** `resources/js/components/sortable-kanban.ts`, `resources/views/pages/tasks/kanban.blade.php`

## Out of Scope

- Dashboard counter card linking to pre-filtered task list (user chose filter bar approach)
- Overdue filter for follow-ups (already works correctly via timeline sections)
- Priority sort on the task list page (task list uses manual sort_order via drag-and-drop)
- Recurring task management UI changes (creation/editing already works)
- Task group color indicator on the inline pill (possible future enhancement)

## Open Questions

_None at this time._
