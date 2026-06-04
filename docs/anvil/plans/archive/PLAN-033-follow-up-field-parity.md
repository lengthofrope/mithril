# PLAN-033: Follow-up Field Parity with Tasks (Priority, Private, Description)

**Created:** 2026-06-04
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** PRD-008

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-008](../prds/PRD-008-follow-up-field-parity.md) | Follow-up Field Parity with Tasks | Approved |

## Problem Statement

Follow-ups lack three capabilities Tasks already have: a priority level, a private content
mask, and an optional long-form description. They are also under-filterable on the overview page
and their within-day ordering ignores priority. This plan implements the *how* for PRD-008,
reusing existing Task patterns (the `Priority` enum, the `privacy-shield` component, the
`inline-select-pill`, the `Filterable` trait, and the dashboard priority-sort expression).

## Acceptance Criteria

Derived from PRD-008 (criteria 1-30). Key checkpoints:

1. Pre-existing follow-ups still display their original short text as the title after migration (no data loss).
2. A new optional long-text `description` body persists, auto-saves, and is searchable.
3. `priority` (urgent/high/normal/low) defaults to `normal`, persists, auto-saves, renders a badge, and is filterable.
4. `is_private` masks the title behind "Private — click to reveal" on cards, the detail page, and the dashboard, exactly mirroring Tasks; it does NOT exclude follow-ups from search or dashboard.
5. Dated sections (overview overdue/today/this-week/later; dashboard today + upcoming) order same-date follow-ups urgent → high → normal → low; the undated Prep section keeps its last-updated order.
6. The overview filter bar gains Priority (select), Private (boolean), and Status (select) filters, combinable with the existing team/member/search.
7. Convert follow-up ↔ task carries title, description, priority, and is_private.
8. Export includes the new fields; legacy import payloads (old `description`-as-title key) map into `title` with no loss.
9. Validation: title required on create, description optional, priority must be a valid enum value, is_private boolean.
10. Migration runs cleanly on MariaDB and SQLite.

## Technical Design

### Approach

The existing `follow_ups.description` column is **renamed** to `title` (the rename carries the
data, satisfying the data-preservation criterion without a separate copy step). A new nullable
`description` TEXT column, a `priority` string column (default `normal`), and an `is_private`
boolean column (default `false`) are added. All code that today reads `FollowUp->description` as
the item label is repointed to `FollowUp->title`. Priority ordering reuses the same `CASE`
expression Tasks use on the dashboard, exposed as a reusable query scope on `FollowUp`.

Frontend reuses existing shared components verbatim: `<x-tl.privacy-shield>` (title masking),
`<x-tl.inline-select-pill>` (priority/status auto-save), and the declarative `<x-tl.filter-bar>`
(no TypeScript changes — filters are config-driven).

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `database/migrations/<new>_add_priority_private_and_split_description_on_follow_ups.php` | Create | Rename `description`→`title`; add `description` TEXT null, `priority` string default `normal`, `is_private` boolean default false. MariaDB + SQLite compatible. |
| `app/Models/FollowUp.php` | Modify | `$fillable` (replace `description` with `title`, add `description`/`priority`/`is_private`); casts (`priority`=>Priority, `is_private`=>boolean); `$searchableFields`=`['title','description','waiting_on']`; `$filterableFields` add `priority`=>exact, `is_private`=>boolean; new `scopePriorityOrdered()`; property docblock. |
| `database/factories/FollowUpFactory.php` | Modify | `title` = short sentence (was `description`); optional long `description`; random `priority`; `is_private` false. |
| `app/Http/Requests/FollowUpRequest.php` | Modify | Validate `title` (required on POST / sometimes), `description` (nullable string), `priority` (`Rule::enum(Priority::class)`), `is_private` (boolean). |
| `app/Http/Controllers/Api/AutoSaveController.php` | Modify | Add `title`, `description`, `priority`, `is_private` to the follow-up field whitelist. |
| `app/Http/Controllers/Web/FollowUpPageController.php` | Modify | `index`: apply priority/is_private/status filters via `applyFilters` + date-then-priority sort on dated sections; pass `priorityOptions`/`statusOptions`; `store`: `title` required + new optional fields; `show` title => `->title`; `convertToTask`: map title/description/priority/is_private. |
| `app/Http/Controllers/Web/DashboardController.php` | Modify | Apply date-then-priority ordering to the today + upcoming follow-up queries (reuse `FollowUp::priorityOrdered()`). Tasks unchanged. |
| `app/Http/Controllers/Web/TaskPageController.php` | Modify | `createFollowUpFromTask`: set `title` (was `description`) and carry `description`/`priority`/`is_private`. |
| `app/Services/MetadataTransferService.php` | Modify | Ensure priority/is_private/description map across convert flows (verify + extend). |
| `app/Http/Controllers/Api/SearchController.php` | Modify | `searchFollowUps`: result label => `->title`; search already covers searchable fields. |
| `app/Services/BreadcrumbBuilder.php` | Modify | `forFollowUp`: label => `->title`. |
| `app/Listeners/CreateFollowUpOnWaiting.php` | Modify | Create with `title` (was `description`). |
| `app/Http/Controllers/Api/ExportImportController.php` | Modify | Export new fields; import maps legacy `description`-as-title key into `title`. |
| `resources/views/components/tl/follow-up-card.blade.php` | Modify | Title via `privacy-shield` when private; priority pill; keep waiting-on/member/date. Mirror `task-card`. |
| `resources/views/pages/follow-ups/show.blade.php` | Modify | Editable title, description textarea, priority pill, private toggle — all auto-save. Mirror `tasks/show`. |
| `resources/views/partials/follow-up-create-modal.blade.php` | Modify | `title` (required), optional description, priority select, private toggle. |
| `resources/views/pages/follow-ups/index.blade.php` | Modify | Add Priority (select), Status (select), Private (boolean) to `<x-tl.filter-bar>`. |
| `resources/views/partials/dashboard/follow-ups.blade.php` | Modify | Priority badge + private masking on dashboard follow-up items. |

### Data Flow

Inline edits POST to the follow-up resource / auto-save endpoints (existing infra). The overview
and dashboard read filtered, priority-ordered queries server-side and return Blade partials.

### Edge Cases & Error Handling

- Existing rows: rename carries values into `title`; new `description` is null — cards render the title.
- Null priority on legacy rows is impossible (default applied), but `priorityOrdered` sorts null last defensively.
- Legacy import payloads using the old `description` key must land in `title`, not the new body.
- Convert flows must not drop any of the four fields in either direction.
- Private follow-ups must remain present (masked) in search and dashboard results.

## Implementation Phases

### Phase 1: Schema & model foundation
- **Goal:** Migration (rename + add columns), FollowUp model contract, factory, request, auto-save whitelist, and a reusable priority-ordering scope ; the data layer everything else builds on.
- **Model:** opus
- **PRD criteria:** 1, 2, 4 (storage), 26-30
- **Specs:**
  - [x] Migration renames `description`→`title` preserving existing values, and adds `description` (TEXT null), `priority` (string default `normal`), `is_private` (boolean default false); runs on SQLite and MariaDB.
  - [x] Test proves a pre-existing row's text survives the rename as `title`.
  - [x] `FollowUp` casts `priority` to `Priority` and `is_private` to boolean; `$fillable` includes `title`, `description`, `priority`, `is_private` (and no longer treats `description` as the label).
  - [x] `$searchableFields` = `title`, `description`, `waiting_on`; `$filterableFields` adds `priority` (exact) and `is_private` (boolean).
  - [x] `FollowUp::priorityOrdered()` scope orders urgent→high→normal→low (null last) via the shared CASE expression.
  - [x] `FollowUpFactory` produces a `title`, optional `description`, a valid `priority`, and `is_private` false.
  - [x] `FollowUpRequest` requires `title` on create, allows nullable `description`, validates `priority` against the enum, and validates `is_private` as boolean.
  - [x] `AutoSaveController` whitelists `title`, `description`, `priority`, `is_private` for follow-ups.
- **Files:** `database/migrations/`, `app/Models/FollowUp.php`, `database/factories/FollowUpFactory.php`, `app/Http/Requests/FollowUpRequest.php`, `app/Http/Controllers/Api/AutoSaveController.php`, `tests/Unit/Models/FollowUpTest.php`, `tests/Feature/Http/Requests/`, `tests/Feature/`

### Phase 2: Backend behavior, sorting, filtering & references
- **Goal:** Repoint all label references from `description` to `title`; apply priority-aware sorting and the new filters on the overview and dashboard; carry the new fields through convert/export/import.
- **Model:** sonnet
- **PRD criteria:** 7, 8, 16-21, 22-25; sorting 5 (server side)
- **Specs:**
  - [x] `FollowUpPageController::index` applies `priority`, `is_private`, and `status` filters (via `applyFilters`) alongside the existing team/member/search, and orders each dated section by `follow_up_date` then `priorityOrdered()`; the Prep section keeps `orderByDesc('updated_at')`.
  - [x] `index` passes `priorityOptions` and `statusOptions` to the view; `store` requires `title` and accepts optional `description`/`priority`/`is_private`; `show` title uses `->title`.
  - [x] Dashboard today + upcoming follow-up queries order by date then `priorityOrdered()` (tasks ordering unchanged).
  - [x] `convertToTask` (FollowUp→Task) and `createFollowUpFromTask` (Task→FollowUp) map title↔title, description↔description, priority↔priority, is_private↔is_private (verify `MetadataTransferService`).
  - [x] `SearchController::searchFollowUps`, `BreadcrumbBuilder::forFollowUp`, and `CreateFollowUpOnWaiting` use `->title` / set `title`.
  - [x] A search matching text only in the `description` body returns the follow-up.
  - [x] `ExportImportController` exports the four fields and imports legacy payloads (old `description` key) into `title` with no loss.
- **Files:** `app/Http/Controllers/Web/FollowUpPageController.php`, `app/Http/Controllers/Web/DashboardController.php`, `app/Http/Controllers/Web/TaskPageController.php`, `app/Services/MetadataTransferService.php`, `app/Http/Controllers/Api/SearchController.php`, `app/Services/BreadcrumbBuilder.php`, `app/Listeners/CreateFollowUpOnWaiting.php`, `app/Http/Controllers/Api/ExportImportController.php`, `tests/Feature/Http/Controllers/`

### Phase 3: Frontend views (cards, detail, create modal, filters, dashboard)
- **Goal:** Surface the new fields in the UI, mirroring Task components; wire the three new overview filters declaratively.
- **Model:** sonnet
- **PRD criteria:** 3, 5 (display), 6, 6 (badge), 11-13, 16-18
- **Specs:**
  - [x] `follow-up-card` renders the title wrapped in `<x-tl.privacy-shield>` when `is_private` (priority/date/status/member stay visible), and shows a priority pill — mirroring `task-card`.
  - [x] `follow-ups/show` exposes an editable title, a description textarea, a priority pill, and a private toggle, all auto-saving with no save button.
  - [x] `follow-up-create-modal` uses `title` (required) and adds optional description, priority select, and private toggle.
  - [x] `follow-ups/index` filter bar includes Priority (select), Status (select), and Private (boolean) filters fed by the controller-provided options.
  - [x] `partials/dashboard/follow-ups` shows the priority badge and applies private masking to private items.
- **Files:** `resources/views/components/tl/follow-up-card.blade.php`, `resources/views/pages/follow-ups/show.blade.php`, `resources/views/partials/follow-up-create-modal.blade.php`, `resources/views/pages/follow-ups/index.blade.php`, `resources/views/partials/dashboard/follow-ups.blade.php`

### Phase 4: Live-refresh priority ordering parity (amendment)
- **Goal:** Make the follow-up live-refresh endpoints apply the same date-then-priority ordering as the full-page load, so changing a follow-up's priority inline reorders the list immediately (via the existing `data-changed` -> `refreshable` re-fetch) without a manual page refresh ; matching the Task dashboard behavior.
- **Model:** sonnet
- **PRD criteria:** 5, 8 (extends the ordering guarantee to the partial-refresh path)
- **Rationale:** `PartialController::followUpsList` and `dashboardFollowUps` (the endpoints the `refreshable` regions poll/re-fetch) sorted only by `follow_up_date`; the date-then-priority sort added in Phase 2 lived only on `FollowUpPageController::index` and `DashboardController`, so live refreshes did not reorder. This was a missed file in the original Phase 2 scope.
- **Specs:**
  - [x] `PartialController::followUpsList` orders each dated section by `follow_up_date` then `priorityOrdered()` (reuse the `FollowUp` scope).
  - [x] `PartialController::dashboardFollowUps` orders `todayFollowUps` and `upcomingFollowUps` by `follow_up_date` then `priorityOrdered()`.
  - [x] A test asserts the `partials.follow-ups` endpoint returns same-date follow-ups urgent-first, and the dashboard follow-up partial likewise.
- **Files:** `app/Http/Controllers/Web/PartialController.php`, `tests/Feature/Http/Controllers/Web/PartialControllerTest.php`

## Parallelization

**Strategy:** Sequential
**Execution method:** Subagents
**Ralph mode:** false

The three phases are strictly dependent: Phase 2 needs the renamed column, scope, and validation
from Phase 1; Phase 3 needs the controller-provided filter options and the repointed `title` from
Phase 2. They also share `FollowUp.php` / `FollowUpPageController.php` ownership across the
backend phases. No concurrency is safe or beneficial. A single Builder agent runs each phase in
order.

## Review

**Review strategy:** per-phase

Phase 1 performs an irreversible column rename with wide blast radius (≈40 files reference
`FollowUp`); a mistake there compounds into Phases 2 and 3. Per-phase review catches schema/model
contract errors before dependent work is built on them.

## Out of Scope

- Any change to Task entity behavior, fields, or UI.
- Hard hiding of private follow-ups (masking only; not excluded from search/dashboard).
- Priority ordering of the undated Prep section.
- Drag-and-drop / manual reordering of follow-ups (no `HasSortOrder`).
- New TypeScript modules (filters are config-driven through the existing `filterManager`).

## Open Questions

- An ADR should be recorded for the `description`→`title` rename (a data-contract change with wide
  blast radius) ; expected ADR-030. The Builder creates it during Phase 1.
- Confirm during Phase 2 whether `MetadataTransferService` already transfers `priority`/`is_private`
  or must be extended; the spec requires the end-to-end mapping regardless.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
| 2026-06-04 | 4 | Added Phase 4: apply date-then-priority ordering in `PartialController::followUpsList` and `dashboardFollowUps`. | User reported follow-ups do not reorder on inline priority change without a manual refresh; the live-refresh partial endpoints were missed in Phase 2's sort work. | — |
