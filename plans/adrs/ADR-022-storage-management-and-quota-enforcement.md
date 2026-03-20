## ADR-022: Storage management with quota enforcement and dual cleanup strategy

**Date:** 2026-03-14
**Phase:** Activity Feed & Polling
**Tags:** backend, frontend, storage, attachments, artisan, settings
**Status:** Accepted

### Context

With the introduction of file attachments (ADR-020), users can upload files that consume server disk space. Without limits or visibility, storage usage could grow unbounded and orphaned files could accumulate silently.

**Requirements:**

1. Users need visibility into their storage usage
2. Per-user upload limits to prevent abuse
3. Cleanup mechanism for orphaned attachments (see also ADR-019)
4. Users should be able to manually trigger cleanup

### Decision

**Quota enforcement:**

- Configurable per-user storage limit via `config('attachments.max_storage_mb')`
- Checked in `ActivityController::createAttachment()` before accepting uploads
- Returns a clear error message when quota is exceeded

**Storage visibility:**

- New settings page section (`SettingsController::storage()`) displays:
  - Total storage used by the user's attachments
  - List of attachments with sizes
  - Count and size of orphaned attachments (where parent activity or activityable is null)
- Users can trigger manual purge of orphaned attachments via `SettingsController::purgeOrphaned()`

**Dual cleanup strategy:**

1. **Scheduled:** `attachments:clean-orphaned` artisan command runs weekly, cleaning up orphaned attachments for all users
2. **Manual:** Users can purge their own orphaned attachments from the settings page on demand

**Orphan detection:**

- Loads `Attachment::with('activity.activityable')` then filters where either the activity or its activityable parent is null
- This catches both direct orphans (activity deleted) and indirect orphans (parent entity deleted but activity remains)

### Consequences

- Storage quota is enforced at upload time only — existing over-quota users are not retroactively affected
- Orphaned attachments may exist for up to one week before scheduled cleanup (or until user triggers manual purge)
- The storage settings page makes additional queries to calculate usage — acceptable for a settings page, not suitable for dashboard display
- Physical file deletion relies on the `Attachment` model's `deleting` event — the cleanup command loads models individually (not bulk delete) to ensure files are removed

### Follow-ups / open questions

- Consider adding admin-level storage overview across all users
- Quota could be made per-user configurable (currently global config value)
