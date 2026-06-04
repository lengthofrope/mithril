# Clarifications: Follow-up parity with Tasks (priority, private, description)

**Source:** user description (`/anvil` feature request, Dutch)
**Author:** Bas de Kort
**Date:** 2026-06-04

## Summary

Follow-ups are currently limited in what can be captured. Bring three Task-grade
capabilities to the `FollowUp` entity, mirroring how Tasks already implement them:
**priority**, **private** (content masking), and a longer **description** body. These
must also surface on the dashboard and on the follow-ups overview page (filters), and
priority must factor into the within-day ordering exactly as the dashboard already does
for tasks.

## Confirmed Scope

### 1. `description` field split (rename + new field)
- Rename the existing required `follow_ups.description` column to **`title`**. It keeps
  its current role: the short, required summary used as the page title and card heading.
- A **data migration must copy existing `description` values into the new `title`**
  column so existing follow-ups keep their visible label after deploy.
- Add a **new `description` column**: nullable long text (`TEXT`), the optional rich body.
  Mirrors `Task.description`.
- Add the new `description` to the model's searchable fields (alongside `title` and
  `waiting_on`).
- Update every reference to the old `description`-as-title usage:
  `FollowUp` model (`$fillable`, `$searchableFields`, property docblock), `FollowUpRequest`,
  `FollowUpPageController` (`store`, `show` title, `convertToTask`, `createFollowUpFromTask`),
  `TaskPageController::createFollowUpFromTask` (sets `description` => `$task->title`),
  `follow-up-card.blade.php` (line ~36 renders the title), follow-ups show page, dashboard
  follow-up partials, `SearchController::searchFollowUps`, `FollowUpFactory`,
  `BreadcrumbBuilder::forFollowUp`, the `CreateFollowUpOnWaiting` listener, the AutoSave
  field whitelist, and the export/import payload (`ExportImportController`).

### 2. `priority`
- Add a `priority` column reusing the existing `App\Enums\Priority` enum
  (`urgent` / `high` / `normal` / `low`). **Default `normal`.**
- Cast to `Priority::class`; add to `$fillable`; add to `$filterableFields` as `'exact'`.
- Render a priority pill on the follow-up card and show page, mirroring `task-card.blade.php`
  (`<x-tl.inline-select-pill>` with the same option/color maps; or `<x-tl.priority-badge>`).
- **Day-sort:** in every *dated* grouping, order by `follow_up_date` then by the existing
  `DashboardController::PRIORITY_SORT_EXPRESSION` (urgent=0 … low=3, null last). Applies to:
  - the follow-ups overview sections: overdue, today, this week, later;
  - the dashboard today section (overdue + due-today follow-ups) and the upcoming follow-ups section.

### 3. `is_private` (mirror Task masking exactly)
- Add an `is_private` boolean column, **default `false`**. Cast `boolean`; add to `$fillable`;
  add to `$filterableFields` as `'boolean'`.
- Reuse the existing `<x-tl.privacy-shield>` component (backed by the `privacyToggle` Alpine
  component). Mirror `task-card.blade.php`: when `is_private`, wrap **the title** in the
  privacy shield ("Private — click to reveal"); the priority pill, date, status, and member
  meta stay visible. Apply the same masking on the follow-ups show page and on dashboard
  follow-up items.

### 4. Overview page filters
- Add **Priority**, **Private**, and **Status** filters to the follow-ups overview filter bar
  (today it only exposes team + member + search). Wire them through the `Filterable` trait /
  `FilterManager` TypeScript / Blade filter partial, returning the `follow-ups-list` partial
  for AJAX requests (the established pattern; note the current `index` does manual filtering and
  will need to integrate the new filters).

### 5. Dashboard surfacing
- Priority badge on dashboard follow-up cards.
- Priority-aware sort (date → priority) in the dashboard today + upcoming follow-up sections.
- Private masking applied to private follow-ups shown on the dashboard.

### 6. Auto-save / API
- New fields are editable inline via the existing AutoSave path (no save buttons); add `title`,
  `description`, `priority`, `is_private` to the AutoSave field whitelist for follow-ups.
- `FollowUpRequest` validates: `title` (required on create / `sometimes` on update, string),
  `description` (nullable string), `priority` (`Rule::enum(Priority::class)`, default normal on
  create), `is_private` (boolean).

## Out of Scope

- Any change to Task behavior.
- Hard hiding of private follow-ups: `private` only masks content via the reveal shield; it does
  NOT exclude follow-ups from global search or from the dashboard query.
- Priority-based ordering of the undated **Prep** section (it keeps its current
  `orderByDesc('updated_at')` sort).
- Adding `HasSortOrder` / drag-drop ordering to follow-ups.

## Acceptance Signals

- After deploy, every pre-existing follow-up still shows its original text as the card/page title
  (migrated into `title`); no follow-up loses its label.
- A follow-up can be given a priority, a long description, and a private flag, all auto-saved.
- On the overview and dashboard, dated follow-ups with the same date are ordered urgent → high →
  normal → low.
- A private follow-up shows "Private — click to reveal" in place of its title on cards, the show
  page, and the dashboard, and reveals on click; priority/date/status remain visible.
- The overview filter bar filters by priority, private, and status (in addition to team, member, search).

## Edge Cases & Behaviors

- **Existing rows:** post-migration, `title` is populated from the old `description`; new
  `description` is `null`. Cards render the title with no empty-state regression.
- **Convert flows:** follow-up ↔ task conversions must map `title` ↔ task `title`,
  follow-up `description` ↔ task `description`, and carry over `priority` and `is_private`
  (verify `MetadataTransferService` and the convert controllers).
- **Search:** `searchFollowUps` and the model's `$searchableFields` cover both `title` and
  `description`.
- **Export/Import:** payload schema includes `title`, `description`, `priority`, `is_private`;
  import of older payloads (with `description`-as-title) must still map correctly into `title`.
- **Null priority:** the sort expression already sorts null last; new rows default to `normal`.

## Data & Integrations

- Database: MariaDB (production) + SQLite (tests). Migration must be compatible with both
  (string column for the enum, not `enum()`; `text` for description; boolean for `is_private`).
- No external integrations involved.

## Non-Functional Requirements

- Auto-save debounced 500ms (existing pattern); no save buttons.
- TDD: Pest tests first (model casts/fillable/filter, migration data backfill, controller
  validation, sort ordering, search).
- TailAdmin design language; reuse existing components (`privacy-shield`, `inline-select-pill`,
  `priority-badge`). No inline styles/JS; `rem`/`em` only.

## Constraints

- Production DB is MariaDB; migrations must run on MariaDB and SQLite. Enums stored as strings.
- The rename touches ~40 files that reference `FollowUp`; the migration is the irreversible step
  (back up / guard the data backfill).

## User Roles & Permissions

- Single-user-scoped via `BelongsToUser` global scope; no new roles. `private` is a personal
  on-screen masking concern, not an authorization boundary.

## Open Questions Deferred

- None.

## Resolved Assumptions

- "Add description like tasks" — resolved: the existing follow-up `description` is the title
  equivalent, so rename it to `title` and add a new long `description` (with data backfill).
- "Private" — resolved: mirror the Task masking (reveal shield on the title), not a hard
  visibility/exclusion rule.
- Priority default — resolved: `normal`, matching tasks.
- Day-sort source of truth — resolved: reuse `DashboardController::PRIORITY_SORT_EXPRESSION`.

## Glossary deltas

- (none — `priority`, `private`, `follow-up`, and `title` already align with existing Task terminology)
