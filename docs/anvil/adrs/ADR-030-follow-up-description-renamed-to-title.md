# ADR-030: Follow-up `description` column renamed to `title`

**Date:** 2026-06-04
**Status:** Accepted
**Tags:** backend, database, migration, data-contract, follow-ups
**Phase:** PLAN-033 Phase 1

## Context

PRD-008 brings follow-ups to field parity with tasks by adding a long-form
`description` body, a `priority` level, and an `is_private` flag, and requires a
Task-style title plus description split. Tasks already model this as a required
`title` and an optional `description`.

Follow-ups historically stored their single, required short label in a column
named `description`. That column already played the exact role of a Task `title`:
it was required, short, and rendered as the item's visible label on cards, the
detail page, the dashboard, search results, and breadcrumbs.

Two approaches were considered:

1. Keep `description` as the label and add a new `long_description` (or similar)
   for the body. Rejected: it permanently diverges follow-up naming from the Task
   convention the whole codebase already follows, making the shared components
   (`privacy-shield`, `inline-select-pill`) and the convert-to/from-task mapping
   awkward and asymmetric.
2. Rename the existing `description` column to `title` (carrying its data) and add
   a fresh nullable `description` TEXT column for the long body. Chosen: it aligns
   follow-ups with the Task contract, the rename itself preserves every existing
   value (no separate backfill step), and downstream convert/search/export logic
   becomes a symmetric title-to-title, description-to-description mapping.

The constraint is a wide blast radius: roughly forty files reference
`FollowUp->description` as the label, and a production MariaDB FULLTEXT index
covers the old `description` column.

## Decision

Rename `follow_ups.description` to `follow_ups.title` via a schema rename, which
carries the stored values and is itself the data-preservation guarantee. Add a new
nullable `description` TEXT column for the long-form body, a `priority` string
column (default `normal`, stored as a string, not a native DB enum), and an
`is_private` boolean column (default `false`).

`title` is the required short label going forward; `description` is the optional
long body. All code that reads `FollowUp->description` as the label is repointed to
`FollowUp->title`. The `FollowUp` model casts `priority` to the existing `Priority`
enum and `is_private` to boolean, searches across `title`, `description`, and
`waiting_on`, filters on `priority` (exact) and `is_private` (boolean), and exposes
a reusable `scopePriorityOrdered()` that applies the same urgent-to-low CASE
expression tasks use on the dashboard.

The production FULLTEXT index `ft_follow_ups_search` is dropped before the rename
and recreated afterwards over `(title, description, waiting_on)`. The drop/recreate
is guarded so SQLite (which lacks FULLTEXT support) is skipped.

## Deviation from Plan

None. This implements PLAN-033 Phase 1 as specified.

## PRD Reference

PRD-008, criteria 1 (data preservation), 2 (new description body), 4 (private
masking storage), and 26-30 (validation and database compatibility). See also
PLAN-033 Phase 1.

## Consequences

### Positive
- Follow-ups now share the Task title/description contract, so shared components,
  search, and convert flows map symmetrically.
- The rename carries existing values, so no follow-up loses its visible label and
  no separate, error-prone backfill step is required.
- Priority ordering reuses the established Task CASE expression via a single scope,
  ready for Phase 2 reuse on the overview and dashboard.

### Negative
- Roughly forty files that reference `FollowUp->description` as the label must be
  repointed to `->title`; until that work lands in Phase 2 the broader test suite
  reports expected failures in those references.
- The column rename is irreversible in practice for production data semantics; the
  `down()` migration reverses the schema but a real revert would reintroduce the
  old single-field model.

### Code/Data Changes
- `FollowUp` model: `$fillable`, `casts()`, `$searchableFields`, `$filterableFields`,
  `scopePriorityOrdered()`, and property docblock updated.
- `FollowUpFactory`, `FollowUpRequest`, and `AutoSaveController` follow-up rules
  updated for the new fields.
- Phase 2 repoints label reads in `FollowUpPageController`, `DashboardController`,
  `TaskPageController`, `SearchController`, `BreadcrumbBuilder`,
  `CreateFollowUpOnWaiting`, `MetadataTransferService`, and `ExportImportController`,
  including legacy import payloads that use the old `description`-as-title key.

### Migration / Operational Impact
- Single migration `2026_06_04_000001_add_priority_private_and_split_description_on_follow_ups`
  renames the column, adds three columns, and drops/recreates the FULLTEXT index.
- Verified to run and roll back cleanly on SQLite; the MariaDB FULLTEXT path mirrors
  the existing `2024_01_01_200000_add_fulltext_indexes` pattern and is SQLite-guarded.
- Legacy export/import payloads must map the old `description` key into `title`
  (handled in Phase 2).
