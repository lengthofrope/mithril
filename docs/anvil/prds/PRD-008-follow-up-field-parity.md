# PRD-008: Follow-up Field Parity with Tasks (Priority, Private, Description)

**Created:** 2026-06-04
**Status:** Approved
**Author:** Bas de Kort
**Source:** docs/anvil/research/CLARIFICATIONS-followup-task-parity.md

## Problem Statement

Follow-ups are the primary lightweight action items tracked by team leads in Mithril, yet they
lack three capabilities that Tasks already provide: a **priority level**, a **private content
mask**, and an **optional long-form description**. This creates an inconsistent experience where
follow-ups cannot express urgency, cannot protect sensitive context, and cannot carry supporting
detail beyond their short one-liner. The gap also means that when a task is converted to a
follow-up (or vice versa), these properties are silently lost.

Additionally, the follow-ups overview page is under-filterable compared to the tasks view, and
the within-day ordering on both the overview and the dashboard does not factor in priority the
way the tasks sections already do.

## In Scope

1. **Title/description field split.** The existing required short-text field is renamed to
   "title". A new optional long-text "description" field is added as the rich body. Existing
   follow-up records are migrated so their current short text becomes the title; no record
   loses its visible label.

2. **Priority.** Follow-ups gain a priority level (urgent / high / normal / low), defaulting to
   normal. A priority badge is rendered on every follow-up card, the follow-up detail page, and
   on dashboard follow-up items.

3. **Private flag.** Follow-ups gain an is-private toggle. When enabled, the title is masked
   behind a "Private — click to reveal" control on cards, the detail page, and dashboard items,
   mirroring the existing Task masking behavior exactly. Priority, date, status, and member meta
   remain visible when a follow-up is private.

4. **Date-grouped priority ordering.** In every view where follow-ups are grouped by date
   (overdue, today, this week, later on the overview page; today and upcoming sections on the
   dashboard), follow-ups sharing the same date are ordered urgent → high → normal → low.

5. **Overview filter bar additions.** The follow-ups overview filter bar gains Priority, Private,
   and Status filters, wired through the same filterable pattern used elsewhere in the
   application.

6. **Dashboard updates.** Dashboard follow-up cards show the priority badge, apply private
   masking, and use the date-then-priority sort order.

7. **Auto-save.** All three new fields (priority, is-private, description) are editable inline
   via the existing auto-save mechanism with no save buttons.

8. **Convert-to/from-task field mapping.** Converting a follow-up to a task, and a task to a
   follow-up, maps title ↔ title, description ↔ description, priority ↔ priority, and
   is-private ↔ is-private.

9. **Search.** The follow-up search covers both the title and the new description field.

10. **Export/import compatibility.** The export payload includes the new fields. Import of
    legacy payloads (where the old "description" key represented the title) maps correctly into
    the title field with no data loss.

## Out of Scope

- Any change to Task entity behavior, fields, or UI.
- Hard hiding of private follow-ups: the private flag only masks content via the reveal control;
  it does not exclude follow-ups from global search results or from dashboard queries.
- Priority ordering of the undated "Prep" section on the follow-ups overview; it retains its
  current last-updated sort.
- Drag-and-drop or manual reordering of follow-ups.
- New user roles or authorization rules; the private flag is a personal on-screen masking
  concern, not a visibility boundary.

## User Roles

| Role | Goals | Key Interactions |
|------|-------|-----------------|
| Team lead (sole user) | Capture richer context for follow-up items; express urgency; protect sensitive titles in shared-screen scenarios; find follow-ups faster by filtering on priority/status/private. | Creates and edits follow-ups with title, description, priority, and private flag; filters the overview; reviews prioritised follow-ups on the dashboard. |

## Acceptance Criteria

1. **Data preservation after deploy.** Every follow-up that existed before the migration still
   displays its original short text as its title on the card and detail page. No follow-up
   shows a blank or missing title post-migration.

2. **New description field persists.** A team lead can type a long-form body into the
   description area of a follow-up and it is saved. The field is optional; leaving it empty
   causes no validation error and no empty-state regression in the UI.

3. **Description auto-saves.** Changes to the description field are persisted automatically
   after a short debounce (no save button required), consistent with all other auto-saved fields
   in the application.

4. **Priority default is normal.** A newly created follow-up (with no explicit priority
   supplied) has a priority of normal.

5. **Priority persists and auto-saves.** A team lead can change the priority of an existing
   follow-up inline; the new value is persisted without a save button.

6. **Priority badge visible.** On a follow-up card (overview page), the follow-up detail page,
   and dashboard follow-up items, a priority badge renders showing the current priority level.

7. **Date-grouped priority ordering — overview.** On the follow-ups overview page, within each
   dated section (overdue, today, this week, later), follow-ups are ordered urgent first, then
   high, then normal, then low. Two follow-ups with the same date and different priorities
   appear in the correct relative order.

8. **Date-grouped priority ordering — dashboard.** On the dashboard, within the today follow-up
   section and within the upcoming follow-up section, follow-ups sharing the same date are
   ordered urgent → high → normal → low.

9. **Prep section order unchanged.** The undated "Prep" section on the follow-ups overview
   continues to order items by last-updated descending; priority has no effect on its ordering.

10. **Private flag persists and auto-saves.** A team lead can toggle the private flag on a
    follow-up and the value is persisted without a save button.

11. **Private masking on the overview card.** When a follow-up is marked private, its card on
    the overview page shows "Private — click to reveal" in place of the title. The priority
    badge, due date, status, and member meta remain visible.

12. **Private masking on the detail page.** When a follow-up is marked private, its detail page
    masks the title with the reveal control. Revealing the title via the control shows the
    actual title.

13. **Private masking on the dashboard.** Dashboard follow-up items that are private display
    the masked title with the reveal control, not the plain title.

14. **Private does not hide from search.** A private follow-up is still returned by search
    queries; it is displayed in results with its title masked, not excluded.

15. **Private does not hide from dashboard queries.** Private follow-ups appear in the dashboard
    today and upcoming sections; they are masked, not absent.

16. **Priority filter on overview.** The follow-ups overview filter bar includes a Priority
    filter. Selecting a priority level restricts the list to follow-ups of that priority.
    Clearing the filter restores all follow-ups.

17. **Private filter on overview.** The follow-ups overview filter bar includes a Private
    filter. Filtering to "private only" shows only private follow-ups; filtering to
    "non-private only" shows only non-private follow-ups; no filter shows all.

18. **Status filter on overview.** The follow-ups overview filter bar includes a Status filter.
    Selecting a status restricts the list to follow-ups of that status. Clearing the filter
    restores all.

19. **Filter combinations work.** Priority, Private, Status, team, member, and search filters
    can be combined; the result set satisfies all active filter conditions simultaneously.

20. **Convert follow-up to task carries new fields.** When a follow-up is converted to a task,
    the task is created with the same title, description, priority, and is-private value as the
    source follow-up.

21. **Convert task to follow-up carries new fields.** When a task is converted to a follow-up,
    the follow-up is created with the same title, description, priority, and is-private value
    as the source task.

22. **Export includes new fields.** An exported payload for a follow-up contains the title,
    description, priority, and is-private fields.

23. **Import of new-format payload restores fields.** Importing a payload that includes title,
    description, priority, and is-private creates the follow-up with all four values correctly
    applied.

24. **Import of legacy payload maps correctly.** Importing a payload in the old format (where
    the key formerly used for the short text was "description") still creates the follow-up
    with that text as the title and no data loss.

25. **Search covers description.** A search query that matches text present only in the
    description body of a follow-up (not in the title or waiting-on fields) returns that
    follow-up in results.

26. **Validation — title required on create.** Attempting to create a follow-up without a title
    returns a validation error for the title field.

27. **Validation — description optional.** Creating or updating a follow-up with no description
    supplied succeeds without a validation error.

28. **Validation — priority must be a valid level.** Submitting an unrecognised priority value
    returns a validation error.

29. **Validation — is-private is boolean.** Submitting a non-boolean value for the private flag
    returns a validation error.

30. **Database compatibility.** The migration runs successfully on both MariaDB (production) and
    SQLite (test environment), with the priority stored as a string column and is-private stored
    as a boolean column.

## Constraints

- The database migration that renames the existing short-text column and backfills data is
  irreversible; the data backfill step is mandatory and must be guarded against data loss.
- Production database is MariaDB; all schema changes must be MariaDB-compatible. The enum must
  be stored as a string column, not a native database enum type.
- Test environment uses SQLite in-memory; all migrations must also run cleanly on SQLite.
- No inline styles, inline JavaScript, or save buttons. Auto-save debounced at 500ms.
- All UI components must conform to the TailAdmin design language; reuse existing shared
  components rather than introducing new visual patterns.

## Dependencies

| Dependency | Impact | Status |
|------------|--------|--------|
| Existing `Priority` enum (urgent / high / normal / low) | Follow-up priority reuses this enum directly; no new enum needed. | Available |
| Existing privacy-shield / reveal component | Private masking reuses this component unchanged; behavior must match the Task implementation exactly. | Available |
| Existing priority badge and inline-select-pill components | Priority rendering reuses these; visual parity with Tasks is the acceptance bar. | Available |
| Existing `Filterable` trait and `FilterManager` TypeScript system | The three new overview filters are wired through this system; no new filter infrastructure needed. | Available |
| Existing `AutoSaver` system and AutoSave field whitelist | New fields are added to the follow-up whitelist; no new auto-save infrastructure needed. | Available |
| Existing priority-sort expression used for Tasks on the dashboard | Follow-up day-grouping sort reuses this expression; ordering logic must be identical. | Available |
| Convert-to/from-task flows | These flows must be updated to carry the new fields; a regression in the convert UX is a blocker. | Requires update |
| Export/Import controller payload schema | Must be updated to include new fields and to handle legacy payloads with the old key name. | Requires update |

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Data preservation | Zero follow-ups with a blank title after migration | Query the database post-migration; count rows where title IS NULL or empty |
| Test coverage | All new acceptance criteria covered by automated Pest tests | Test suite passes with no skipped tests for new follow-up behavior |
| Auto-save reliability | New fields save on the same debounce path as existing fields | Manual smoke test: edit priority, description, private flag; verify persisted values without a page reload |
| Filter correctness | Each new filter (priority, private, status) returns only matching results | Automated controller/filter tests; manual verification with known fixture data |
| Sort correctness | Within a date group, urgent always precedes high, high precedes normal, normal precedes low | Automated sort-order test with follow-ups of mixed priorities sharing the same date |
| Convert fidelity | Converted follow-up ↔ task carries all four new fields with no data loss | Automated conversion tests asserting each field on the created record |

## Changelog

| Date | Change | Reason |
|------|--------|--------|
| 2026-06-04 | Initial draft | — |
