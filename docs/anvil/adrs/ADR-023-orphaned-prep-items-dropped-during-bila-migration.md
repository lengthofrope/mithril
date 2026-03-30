## ADR-023: Orphaned prep items dropped during Bila→Meeting migration

**Date:** 2026-03-18
**Phase:** Meetings (Phase 1)
**Tags:** backend, migration, data-integrity, meetings
**Status:** Accepted

### Context

The legacy `bila_prep_items` table allowed `bila_id` to be `NULL`. This enabled "floating" prep items — items created for a team member but not yet assigned to a specific bila session. Users could create prep items in advance and link them to a bila later.

The new `meeting_prep_items` table requires a non-nullable `meeting_id` foreign key with cascade delete. This is by design: prep items are always scoped to a specific meeting, and a meeting must exist before prep items can be added to it.

During the data migration from `bilas` → `meetings`, all prep items linked to a bila are successfully migrated. However, orphaned prep items (`bila_id = NULL`) cannot be migrated because there is no meeting to attach them to.

Alternatives considered:
1. **Create placeholder meetings** for orphaned items — rejected as it would create ghost meetings with no scheduled date or attendee
2. **Make `meeting_id` nullable** — rejected as it contradicts the new design where prep items are always meeting-scoped
3. **Drop orphaned items** — accepted, as these are draft items that were never linked to a session

### Decision

Orphaned prep items (`bila_id = NULL`) are dropped during the Bila→Meeting data migration. A comment in the migration documents this decision. No user notification is generated.

### Deviation from plan

The plan specifies migrating `bila_prep_items → meeting_prep_items` but does not explicitly address the nullable `bila_id` edge case. The implementation drops items that cannot be migrated rather than creating synthetic meetings.

### Consequences

- **Data loss:** Any floating prep items in production will be permanently deleted during migration. In practice this is low-risk — these items were temporary drafts.
- **No rollback path:** The `drop_bila_tables` migration is destructive. A database backup before migration is essential.
- **New workflow:** Users must create a meeting first, then add prep items to it. The old "add prep item for a member without a meeting" flow no longer exists.

### Follow-ups / open questions

- Verify with production data how many orphaned prep items exist before running the migration.
