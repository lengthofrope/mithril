## ADR-019: Remove FK constraint on attachments.activity_id

**Date:** 2026-03-14
**Phase:** Activity Feed & Polling
**Tags:** backend, database, attachments, data-integrity
**Status:** Accepted

### Context

The original plan specified `attachments.activity_id` as a foreign key with `cascadeOnDelete` to `activities.id`. This ensures referential integrity — deleting an activity automatically cascades to its attachments at the database level.

However, the plan also specifies a `CleanOrphanedAttachments` artisan command that detects and deletes attachments whose parent activity no longer exists. With a strict FK cascade, this command would have nothing to do — orphans are structurally impossible.

**Alternatives considered:**
1. **Keep FK cascade, remove cleanup command** — Maximum data integrity, but loses the safety net for edge cases (raw SQL operations, failed transactions between file write and DB commit, future data imports).
2. **Keep FK cascade, test cleanup command differently** — The command would exist but never trigger in normal use; tests would need to bypass FK constraints, making them artificial.
3. **Remove FK, keep cleanup command** — Trades DB-level referential integrity for application-level cleanup. The `Attachment` model's `deleting` event and the `BelongsToUser` relationship still enforce correctness in normal Eloquent flows.

Option 3 was chosen.

### Decision

`attachments.activity_id` is a plain `unsignedBigInteger` column without a foreign key constraint. The `Attachment::activity()` BelongsTo relationship still works for Eloquent queries. Data integrity is enforced at the application layer:

- **Normal flow:** Deleting an activity triggers Eloquent's `deleting` event on each attachment (which cleans up physical files), then the DB delete cascades through application code.
- **Edge cases:** The `attachments:clean-orphaned` weekly command catches any orphans that slip through (raw queries, failed transactions, imports).
- **User deletion:** `user_id` retains its FK with `cascadeOnDelete`, so deleting a user still purges all their attachments at the DB level.

### Consequences

- Orphaned attachment rows can temporarily exist in the database (cleaned weekly).
- No DB-level guarantee that `activity_id` points to a valid activity — application must handle gracefully.
- The cleanup command has a real purpose and is testable without bypassing constraints.
